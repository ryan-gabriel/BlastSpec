---
status: approved
owner: Platform
last_verified: 2026-11-02
source_of_truth_for:
  - environment variables
  - configuration defaults
related:
  - DEPLOYMENT.md
  - SECURITY.md
  - .env.example
---

# Environment

`.env.example` is the canonical template. This document explains each variable, its owner, and its validation.

## 1. Rules

1. `.env` is never committed. `.env.example` contains placeholders only.
2. Application code reads config via `config()`, never `env()`.
3. The app validates required variables at boot and fails fast with a readable error.
4. Every secret is at least 32 random bytes in production and differs from local.
5. Frontend variables are public by definition; never put a secret behind `NEXT_PUBLIC_`.
6. Changing `NEXT_PUBLIC_API_URL` requires rebuilding the frontend image.
7. Graph and worker services must receive the same `GRAPH_SERVICE_TOKEN` and `WEBHOOK_SECRET`.
8. Redis must run with `maxmemory-policy noeviction` so stream entries are never evicted.

---

## 2. Application

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `APP_NAME` | API, worker | Yes | `BlastSpec` | No | Display name |
| `APP_ENV` | API, worker | Yes | `local` | No | `local`, `testing`, `production` |
| `APP_KEY` | API, worker | Yes | — | **Yes** | Laravel encryption key (`php artisan key:generate`) |
| `APP_DEBUG` | API, worker | Yes | `false` | No | Never `true` in production |
| `APP_URL` | API | Yes | `http://localhost:8000` | No | Public API origin |
| `APP_TIMEZONE` | API, worker | Yes | `UTC` | No | Must remain `UTC` |
| `APP_LOCALE` | API | No | `en` | No | UI/API messages |
| `LOG_CHANNEL` | API, worker, graph | No | `stack` | No | `stack`, `stderr`, `json` |
| `LOG_LEVEL` | API, worker, graph | No | `info` | No | `debug` is local only |

## 3. Database

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `DB_CONNECTION` | API, worker | Yes | `pgsql` | No | Only `pgsql` is supported |
| `DB_HOST` | API, worker | Yes | `postgres` | No | Host |
| `DB_PORT` | API, worker | Yes | `5432` | No | Port |
| `DB_DATABASE` | API, worker | Yes | `blastspec` | No | Database name |
| `DB_USERNAME` | API, worker | Yes | `blastspec` | No | User |
| `DB_PASSWORD` | API, worker | Yes | — | **Yes** | Password |

## 4. Redis

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `REDIS_CLIENT` | API, worker | No | `phpredis` | No | `phpredis` or `predis` |
| `REDIS_HOST` | API, worker | Yes | `redis` | No | Host |
| `REDIS_PORT` | API, worker | Yes | `6379` | No | Port |
| `REDIS_PASSWORD` | API, worker | No | `null` | **Yes** (if set) | Password |
| `REDIS_DB` | API, worker | No | `0` | No | Streams/queue database |
| `REDIS_CACHE_DB` | API, worker | No | `1` | No | Cache database |
| `REDIS_PREFIX` | API, worker | No | `blastspec` | No | Key prefix |
| `STREAM_PREFIX` | API, worker | No | `blastspec` | No | Stream name prefix |

## 5. Graph service

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `GRAPH_SERVICE_URL` | API, worker | Yes | `http://graph-service:8001` | No | Internal base URL, no trailing slash |
| `GRAPH_SERVICE_TOKEN` | API, worker, graph | Yes | — | **Yes** | Shared internal token, ≥ 32 chars |
| `GRAPH_TIMEOUT_SECONDS` | API, worker | No | `10` | No | Overall request timeout |
| `KUZU_PATH` | graph | Yes | `/data/kuzu` | No | Writable directory for the embedded DB |
| `GRAPH_MAX_NODES` | graph | No | `100000` | No | Reject larger graphs |
| `GRAPH_MAX_EDGES` | graph | No | `500000` | No | Reject larger graphs |
| `GRAPH_NODE_RETURN_LIMIT` | graph | No | `50000` | No | Maximum nodes returned in one response |

## 6. Webhooks

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `WEBHOOK_SECRET` | API, worker | Yes | — | **Yes** | HMAC key for CI webhooks, ≥ 32 chars |
| `WEBHOOK_PREVIOUS_SECRET` | API, worker | No | `null` | **Yes** | Accepted during rotation for 24 h |
| `WEBHOOK_TOLERANCE_SECONDS` | API, worker | No | `300` | No | Timestamp skew window |

## 7. Auth and demo workspace

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `SESSION_DRIVER` | API | No | `redis` | No | `redis` or `cookie` |
| `SESSION_LIFETIME` | API | No | `480` | No | Idle minutes |
| `SESSION_SECURE_COOKIE` | API | No | `true` in production | No | HTTPS-only cookie |
| `DEMO_USER_EMAIL` | API, seed | No | `demo@blastspec.dev` | No | Seeded demo login |
| `DEMO_USER_PASSWORD` | API, seed | No | `demo-password` | **Yes** (demo) | Seeded password; change for any public demo |
| `DEMO_WORKSPACE_NAME` | API, seed | No | `Demo workspace` | No | Seeded workspace name |

## 8. CORS and rate limits

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `CORS_ALLOWED_ORIGINS` | API | Yes | `http://localhost:3000` | No | Comma-separated exact origins |
| `RATE_LIMIT_API_PER_MINUTE` | API | No | `120` | No | Session requests |
| `RATE_LIMIT_LOGIN_PER_MINUTE` | API | No | `5` | No | Login per IP |
| `RATE_LIMIT_UPLOAD_PER_MINUTE` | API | No | `10` | No | Uploads per workspace |
| `RATE_LIMIT_WEBHOOK_PER_MINUTE` | API | No | `60` | No | Webhooks per token |

## 9. Outbox and streams

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `OUTBOX_POLL_INTERVAL_MS` | worker-relay | No | `1000` | No | Relay poll interval |
| `OUTBOX_BATCH_SIZE` | worker-relay | No | `50` | No | Rows per claim |
| `OUTBOX_MAX_ATTEMPTS` | worker-relay | No | `10` | No | Publish attempts before marking failed |
| `STREAM_PENDING_TIMEOUT_SECONDS` | worker-streams | No | `60` | No | Idle time before `XAUTOCLAIM` |
| `STREAM_MAX_ATTEMPTS` | worker-streams | No | `3` | No | Attempts before DLQ |
| `STREAM_BACKOFF_SECONDS` | worker-streams | No | `2,10,30` | No | Retry delays with jitter |

## 10. Frontend

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `NEXT_PUBLIC_API_URL` | frontend | Yes | `http://localhost:8000/api/v1` | No | Public API base URL |
| `NEXT_PUBLIC_APP_NAME` | frontend | No | `BlastSpec` | No | Display name |

## 11. Observability (optional)

| Variable | Service | Required | Default | Secret | Description |
|---|---|---|---|---|---|
| `SENTRY_DSN` | API, worker, frontend | No | empty | No | Error reporting |
| `METRICS_ENABLED` | API, worker, graph | No | `false` | No | Expose metrics endpoint |

---

## 12. Boot validation

The API MUST refuse to start when:

- `APP_KEY` is empty or still the example value in production.
- `DB_*` is missing.
- `GRAPH_SERVICE_TOKEN` is shorter than 32 characters.
- `WEBHOOK_SECRET` is shorter than 32 characters.
- `CORS_ALLOWED_ORIGINS` contains `*` while credentials are enabled.
- `APP_DEBUG=true` in production.
- `DEMO_USER_PASSWORD` is the default in production without an explicit override flag.

The graph service MUST refuse to start when:

- `KUZU_PATH` is missing or not writable.
- `GRAPH_SERVICE_TOKEN` is shorter than 32 characters.

The frontend build MUST fail when `NEXT_PUBLIC_API_URL` is missing.

---

## 13. Environment matrix

| Variable | Local | CI | Production |
|---|---|---|---|
| `APP_ENV` | `local` | `testing` | `production` |
| `APP_DEBUG` | `true` | `true` | `false` |
| `DB_HOST` | `postgres` | service host | managed |
| `REDIS_HOST` | `redis` | service host | managed |
| `KUZU_PATH` | `/data/kuzu` | temp dir | `/data/kuzu` |
| `SESSION_SECURE_COOKIE` | `false` | `false` | `true` |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:3000` | `http://localhost:3000` | Vercel domain |
| `DEMO_USER_PASSWORD` | `demo-password` | fixture | rotated |

---

## 14. Rotation

| Secret | Procedure |
|---|---|
| `APP_KEY` | Re-encrypt encrypted columns, then deploy |
| `DB_PASSWORD` | Rotate in provider, update secret, redeploy |
| `REDIS_PASSWORD` | Rotate in provider, update secret, redeploy |
| `GRAPH_SERVICE_TOKEN` | Update both API and graph, restart both |
| `WEBHOOK_SECRET` | Dual-secret window, 24 h (see `DEPLOYMENT.md` §6.3) |
| CI token | Create new, update CI, revoke old |
