---
status: approved
owner: Platform
last_verified: 2026-11-02
source_of_truth_for:
  - local topology
  - deployment topology
  - operational runbooks
related:
  - ENVIRONMENT.md
  - ARCHITECTURE.md
  - SECURITY.md
  - .env.example
---

# Deployment

## 1. Topology

```text
Local / Compose
├── frontend        :3000   Next.js
├── backend         :8000   Laravel API
├── worker-relay            outbox relay
├── worker-streams          Redis Streams consumers
├── graph-service   :8001   FastAPI + embedded Kuzu + volume
├── postgres        :5432
└── redis           :6379

Production
├── Vercel                  frontend
├── Fly.io app: api         Laravel API (public)
├── Fly.io app: worker      relay + stream consumers (private)
├── Fly.io app: graph       FastAPI (private) + Kuzu volume
├── Managed Postgres
└── Managed Redis
```

There is **no Kuzu container**. Kuzu is embedded in `graph-service`.

---

## 2. Local Docker Compose

File: `infra/docker-compose.yml`.

```yaml
name: blastspec

services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE:-blastspec}
      POSTGRES_USER: ${DB_USERNAME:-blastspec}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-blastspec}
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-blastspec} -d ${DB_DATABASE:-blastspec}"]
      interval: 5s
      timeout: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    command: ["redis-server", "--appendonly", "yes"]
    ports:
      - "6379:6379"
    volumes:
      - redisdata:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

  graph-service:
    build:
      context: ../graph-service
    environment:
      KUZU_PATH: /data/kuzu
      GRAPH_SERVICE_TOKEN: ${GRAPH_SERVICE_TOKEN}
      LOG_LEVEL: ${LOG_LEVEL:-info}
    ports:
      - "8001:8001"
    volumes:
      - kuzu-data:/data/kuzu
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://localhost:8001/internal/health"]
      interval: 10s
      timeout: 5s
      retries: 10

  backend:
    build:
      context: ../backend
    env_file:
      - ../.env
    environment:
      DB_HOST: postgres
      REDIS_HOST: redis
      GRAPH_SERVICE_URL: http://graph-service:8001
    ports:
      - "8000:8000"
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
      graph-service:
        condition: service_healthy
    command: ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

  worker-relay:
    build:
      context: ../backend
    env_file:
      - ../.env
    environment:
      DB_HOST: postgres
      REDIS_HOST: redis
      GRAPH_SERVICE_URL: http://graph-service:8001
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    command: ["php", "artisan", "outbox:relay"]

  worker-streams:
    build:
      context: ../backend
    env_file:
      - ../.env
    environment:
      DB_HOST: postgres
      REDIS_HOST: redis
      GRAPH_SERVICE_URL: http://graph-service:8001
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
      graph-service:
        condition: service_healthy
    command: ["php", "artisan", "streams:consume", "graph", "diff", "impact", "webhook"]

  frontend:
    build:
      context: ../frontend
      args:
        NEXT_PUBLIC_API_URL: ${NEXT_PUBLIC_API_URL:-http://localhost:8000/api/v1}
    environment:
      NEXT_PUBLIC_API_URL: ${NEXT_PUBLIC_API_URL:-http://localhost:8000/api/v1}
    ports:
      - "3000:3000"
    depends_on:
      - backend

volumes:
  pgdata:
  redisdata:
  kuzu-data:
```

Notes:

- `kuzu-data` is mounted only into `graph-service`. Do not share it.
- Backend and workers use the same image with different commands.
- The graph service port is published locally for debugging; it is private in production.
- `NEXT_PUBLIC_API_URL` is inlined at build time; rebuilding is required to change it.

### Bootstrap

```bash
cp .env.example .env
docker compose -f infra/docker-compose.yml up --build -d
docker compose -f infra/docker-compose.yml exec backend php artisan key:generate
docker compose -f infra/docker-compose.yml exec backend php artisan migrate --seed
```

### Reset

```bash
docker compose -f infra/docker-compose.yml down -v
```

`-v` deletes Postgres, Redis, and Kuzu volumes. Use only for local reset.

---

## 3. Dockerfiles

### Backend

- Base: `php:8.3-fpm-alpine` (or `php:8.3-cli-alpine` for a simple serve setup).
- Install: `pdo_pgsql`, `redis`, `intl`, `mbstring`, `pcntl`, `opcache`.
- Composer install with `--no-dev --optimize-autoloader`.
- Run as a non-root user.
- Expose 8000.
- Health check: `GET /api/v1/health`.

### Graph service

- Base: `python:3.12-slim`.
- Install `uv`, sync with the lockfile.
- Run as a non-root user.
- `/data/kuzu` is the only writable path.
- Expose 8001.
- Health check: `GET /internal/health`.

### Frontend

- Multi-stage: `node:22-alpine` build → `node:22-alpine` runtime with `next start`.
- `NEXT_PUBLIC_API_URL` passed as a build arg.
- Run as a non-root user.
- Expose 3000.

---

## 4. Fly.io

### 4.1 Apps

| App | Public | Notes |
|---|---|---|
| `blastspec-api` | Yes | Laravel API + `/api/v1/health` |
| `blastspec-worker` | No | Relay and stream consumers; no services |
| `blastspec-graph` | No | FastAPI; private networking only; volume for Kuzu |

### 4.2 Graph app

```toml
app = "blastspec-graph"
primary_region = "sin"

[build]
  dockerfile = "Dockerfile"

[env]
  KUZU_PATH = "/data/kuzu"
  PORT = "8001"

[[mounts]]
  source = "kuzu_data"
  destination = "/data"

[http_service]
  internal_port = 8001
  force_https = false
  auto_stop_machines = false
  min_machines_running = 1
```

- **Exactly one machine.** Kuzu is single-writer. Do not autoscale.
- Health check: `GET /internal/health`.
- Attach the volume in the same region; Kuzu data is region-bound.
- Set `GRAPH_SERVICE_TOKEN` via `fly secrets set`.

### 4.3 Worker app

```toml
app = "blastspec-worker"
primary_region = "sin"

[processes]
  relay = "php artisan outbox:relay"
  streams = "php artisan streams:consume graph diff impact webhook"
```

- No `[http_service]`.
- Same image as the API.
- Set `DB_*`, `REDIS_*`, `GRAPH_SERVICE_URL=http://blastspec-graph.internal:8001`, `GRAPH_SERVICE_TOKEN`.
- Do not scale `streams` beyond what the consumer group can handle; start with 1.

### 4.4 API app

- Public `http_service`, internal port 8000.
- `fly secrets set APP_KEY=... DB_* REDIS_* GRAPH_SERVICE_TOKEN WEBHOOK_SECRET`.
- Run migrations as a release command: `php artisan migrate --force`.
- Health check: `/api/v1/health` with a 10 s grace period.

### 4.5 Postgres and Redis

- Fly Managed Postgres or a free-tier provider.
- Redis must support Streams, consumer groups, and `XAUTOCLAIM` (Redis 6.2+; use 7).
- Do not use an eviction policy that can drop stream keys. Set `maxmemory-policy noeviction` or size the instance so eviction never occurs.

### 4.6 Deploy order

```text
1. Postgres + Redis provisioned
2. graph app deployed + volume attached
3. api app deployed + migrate
4. worker app deployed
5. frontend deployed to Vercel
```

### 4.7 Vercel

- Root directory: `frontend`.
- Build: `npm ci && npm run build`.
- Env: `NEXT_PUBLIC_API_URL=https://api.blastspec.dev/api/v1`.
- Set the API CORS origin to the Vercel domain.
- No server-side secrets in the frontend.

---

## 5. Health and readiness

| Service | Endpoint | Checks |
|---|---|---|
| API | `/api/v1/health` | Postgres, Redis, graph service |
| Graph | `/internal/health` | Kuzu open, path writable |
| Worker | No HTTP | Log heartbeat every 30 s; alert if absent |
| Frontend | `/` | 200 and render |

Expected status semantics:

- `ok`: all dependencies healthy.
- `degraded`: API serves reads but async processing is impaired (for example Redis down).
- `down`: cannot serve the core flow.

---

## 6. Runbooks

### 6.1 Rebuild the graph

1. Confirm the graph service is healthy.
2. `POST /api/v1/specs/{id}/graph/rebuild` or run `php artisan graph:rebuild --all`.
3. Watch `graph_builds` and worker logs.
4. Verify `graph_version` matches the latest input hash.
5. If sync fails, check Kuzu disk space, volume mount, and the internal token.

### 6.2 Replay a DLQ job

1. Open `/settings/webhooks` → Dead letter queue.
2. Inspect the error and the linked resource.
3. Fix the cause.
4. **Replay**.
5. Confirm exactly one new attempt and no duplicate changes/reports.
6. If replay fails again, the job returns to the DLQ with `retry_count + 1`.

### 6.3 Rotate the webhook secret

1. Set `WEBHOOK_PREVIOUS_SECRET` to the current value.
2. Set `WEBHOOK_SECRET` to the new value.
3. Deploy. Both are accepted for 24 hours.
4. Update CI to sign with the new secret.
5. After 24 hours, remove `WEBHOOK_PREVIOUS_SECRET`.

### 6.4 Rotate a CI token

1. Create a new token with the same scopes.
2. Update CI secrets.
3. Verify a successful webhook.
4. Revoke the old token.
5. Confirm `last_used_at` stops updating.

### 6.5 Restore Postgres

1. Restore the snapshot into a new database.
2. Point the API at it.
3. Rebuild the graph from scratch (the graph is derived).
4. Replay pending outbox rows.
5. Verify a known report matches its golden fixture.

### 6.6 Lose the Kuzu volume

1. The API stays up; graph reads return `503 ERR-1401`.
2. Recreate the directory and mount.
3. Restart the graph service.
4. Queue a full rebuild for every workspace.
5. Verify `graph_version` and a sample impact report.

### 6.7 Redis loss

1. Outbox rows remain in Postgres; nothing is lost from the domain.
2. In-flight stream messages are lost; resources may be stuck `running`.
3. Restart Redis.
4. Mark stuck `running` resources older than 10 minutes as `failed`.
5. Requeue their outbox rows (or retry the resource).
6. Confirm the relay drains and statuses resolve.

### 6.8 Stale graph

1. `GET /specs/{id}/graph` returns `stale: true`.
2. Trigger a rebuild.
3. Do not run impact analysis until it completes.

---

## 7. Backups and retention

| Data | Backup |
|---|---|
| Postgres | Daily automated snapshot; 7-day retention for demo |
| Kuzu | None required; rebuild from Postgres |
| Redis | AOF enabled locally; not a backup target |
| Spec raw content | Included in Postgres backup |

Restore test: monthly, restore into a scratch database and rebuild the graph.

---

## 8. Free-tier notes

- Vercel Hobby for the frontend.
- Fly.io shared-cpu-1x machines for API, worker, and graph.
- Graph machine must stay running (`min_machines_running = 1`) to keep Kuzu warm and avoid cold-start locks.
- Managed Postgres/Redis free tiers may sleep; health checks should tolerate a cold start.
- Kuzu data counts against the Fly volume; demo scale is small (< 100 MB).

---

## 9. Observability

- Structured JSON logs shipped to the provider.
- Correlation: `trace_id` across API → outbox → worker → graph.
- Metrics to watch:
  - outbox pending count and age
  - stream pending entries and consumer lag
  - DLQ size
  - graph sync duration and failures
  - diff duration
  - impact analysis duration
  - 5xx rate and `ERR-14xx` rate
- Alerts:
  - outbox pending > 100 for 5 minutes
  - DLQ > 0
  - graph sync failure rate > 5%
  - API 5xx > 1% for 5 minutes
  - worker heartbeat missing for 2 minutes

---

## 10. Release checklist

- [ ] Migrations reviewed and forward-only.
- [ ] `contracts/openapi.yaml` validated and generated types current.
- [ ] Backend, graph, and frontend gates green.
- [ ] E2E happy path and webhook flow green.
- [ ] Secrets set for the target environment; none committed.
- [ ] Graph volume attached and writable.
- [ ] CORS origin updated for the frontend domain.
- [ ] Health checks passing.
- [ ] Seed data present.
- [ ] DLQ empty.
- [ ] Rollback plan: redeploy previous image; migrations are additive for one release.
