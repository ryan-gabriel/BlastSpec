---
status: approved
owner: Engineering
last_verified: 2026-11-02
source_of_truth_for:
  - agent workflow
  - read order
  - repository commands
related:
  - RULES.md
  - PRD.md
  - TASKS.md
  - GLOSSARY.md
---

# AGENTS.md

You are working on **BlastSpec**, an API Contract Change Impact Analyzer: upload OpenAPI specs, build a service-dependency graph, detect breaking changes, and produce a migration-ready impact report (affected services, owners, migration waves).

**This file is the entry point.** Do not read every document. Use the table in §2 to load only what the task needs.

---

## 1. Non-negotiable facts

1. **Postgres is the system of record.** Kuzu is derived and fully rebuildable.
2. **Kuzu is embedded** inside the FastAPI graph service. There is no Kuzu server and no public Kuzu port.
3. **Only the graph service opens Kuzu.** Only Laravel writes Postgres domain tables.
4. **Every domain write and its outbox row commit in one transaction.**
5. **Redis Streams are transport, not storage.** Acknowledge only after the Postgres commit.
6. **Async handlers are idempotent.** At-least-once delivery is expected.
7. **A cycle is never given a fake linear migration order.** Report the SCC and coordinate.
8. **`contracts/openapi.yaml` is the canonical HTTP contract.**
9. **The demo uses a single workspace, session auth, and hashed CI tokens.**
10. **Never edit `Project Plan.md`.** It is the original brief, not a living document.

---

## 2. Read order

| Task | Read, in order |
|---|---|
| Any task | `AGENTS.md` → `GLOSSARY.md` → `RULES.md` |
| Product/scope question | `PRD.md` → `TASKS.md` |
| Add or change an endpoint | `contracts/openapi.yaml` → `API_CONTRACT.md` → `DATA_MODEL.md` → `RULES.md` |
| Database or model change | `DATA_MODEL.md` → `ARCHITECTURE.md` → `RULES.md` → `TESTING.md` |
| Diff rule or severity | `BREAKING_CHANGES.md` → `TESTING.md` → `DATA_MODEL.md` |
| Graph query or algorithm | `GRAPH_ALGORITHMS.md` → `DATA_MODEL.md` → `ARCHITECTURE.md` |
| Graph UI | `DESIGN.md` → `docs/SCREENS.md` → `docs/adr/0007-graph-visualization-stack.md` |
| Any UI | `DESIGN.md` → `docs/SCREENS.md` → `RULES.md` §6 |
| Async, webhook, outbox, DLQ | `ARCHITECTURE.md` → `API_CONTRACT.md` §5–6 → `SECURITY.md` |
| Auth, tokens, upload safety | `SECURITY.md` → `API_CONTRACT.md` → `RULES.md` |
| Deployment or env | `DEPLOYMENT.md` → `ENVIRONMENT.md` → `SECURITY.md` |
| Unclear decision | `docs/adr/README.md` → the relevant ADR |

If two documents disagree, stop and fix the owning document in the same PR. Source-of-truth hierarchy is in `PRD.md` and the front matter of each doc.

---

## 3. Repository map

```text
frontend/        Next.js App Router UI (port 3000)
backend/         Laravel API + workers (port 8000)
graph-service/   FastAPI + embedded Kuzu (port 8001, internal)
contracts/       Canonical OpenAPI document
infra/           docker-compose, Dockerfiles, perf scripts
docs/            Screen specs + ADRs
*.md             Normative engineering docs; start with this file
```

---

## 4. Golden commands

Run from the repo root unless noted.

### Full stack

```bash
docker compose -f infra/docker-compose.yml up --build
docker compose -f infra/docker-compose.yml down -v
docker compose -f infra/docker-compose.yml exec backend php artisan migrate --seed
```

### Backend

```bash
cd backend
composer install
php artisan migrate:fresh --seed
php artisan serve --port=8000
php artisan test
vendor/bin/pest --coverage --min=80
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
```

Workers (separate terminals or compose services):

```bash
php artisan outbox:relay
php artisan streams:consume graph diff impact webhook
```

### Graph service

```bash
cd graph-service
uv sync --all-extras
uv run uvicorn graph_service.main:app --reload --port 8001
uv run pytest
uv run pytest --cov=src --cov-branch --cov-fail-under=90
uv run ruff check .
uv run ruff format --check .
uv run mypy src
```

### Frontend

```bash
cd frontend
npm ci
npm run dev
npm run lint
npm run typecheck
npm run test
npx playwright test
```

### Contract and docs

```bash
npx @redocly/cli lint contracts/openapi.yaml
npm run api:generate
git diff --exit-code frontend/src/lib/api/schema.d.ts
npx markdownlint-cli2 "**/*.md"
```

If a command does not exist yet, the task that adds the tooling owns creating it and updating this section.

---

## 5. Work loop

1. **Pick a task.** Choose the lowest-numbered unblocked `TASK-###` in `TASKS.md`. Do not invent work outside it.
2. **Read the linked requirements and docs** using §2.
3. **Restate acceptance criteria** in the PR description as a checklist.
4. **Write a failing test first** (or explain why the task is docs/config only).
5. **Implement the smallest vertical change** that satisfies the task.
6. **Run the gates** for touched services: lint, typecheck, tests, contract drift.
7. **Update owning docs** if behavior, schema, contract, rule, or UI changed.
8. **Commit** with a Conventional Commit and open a PR using the checklist in §8.

### When to decide vs stop

| Situation | Action |
|---|---|
| Reversible implementation detail (naming, file split, local helper) | Decide; note it in the PR |
| Adds/removes a dependency, endpoint, table, queue, or service | Stop and ask; consider an ADR |
| Changes a severity, contract, schema, or public behavior | Update the owning doc first; if it changes the product, ask |
| Contradiction between docs | Stop; fix the owning doc, then proceed |
| Test failure that seems "unrelated" | Investigate; never skip or weaken it |
| Missing credentials or external service | Use local compose; never add real secrets |

---

## 6. Definition of Done

A task is complete only when all are true:

- [ ] Acceptance criteria in `TASKS.md` are met.
- [ ] Tests added/updated and passing; coverage gates hold.
- [ ] `contracts/openapi.yaml` updated and generated types committed when HTTP changed.
- [ ] `DATA_MODEL.md` updated when schema changed.
- [ ] `BREAKING_CHANGES.md` updated when a rule/severity changed.
- [ ] `GRAPH_ALGORITHMS.md` updated when algorithm semantics changed.
- [ ] `DESIGN.md` / `docs/SCREENS.md` updated when UI changed.
- [ ] Errors use documented `ERR-###` codes.
- [ ] Logs include `trace_id`, exclude secrets and raw specs.
- [ ] No new lint/type/security warnings.
- [ ] Accessibility checked for new UI.
- [ ] No `console.log`, `dd()`, `dump()`, `var_dump()`, or commented-out code.
- [ ] No generated file edited by hand.
- [ ] PR links `TASK-###` and relevant `FR-###`.

Full checklist: `RULES.md` §13.

---

## 7. Guardrails

### Never

- Start a Kuzu server container or expose port 7272.
- Write to Kuzu outside `graph-service/src/graph_service/graph/repository.py`.
- Read/write Postgres from the graph service.
- Publish to Redis before the Postgres transaction commits.
- Add an async handler that is not idempotent.
- Fabricate a migration order for a dependency cycle.
- Hand-edit `frontend/src/lib/api/schema.d.ts`.
- Weaken, skip, or delete a failing test to make CI green.
- Add `any`, `@ts-ignore`, `as unknown as`, `mixed`, or a silenced exception.
- Commit `.env`, tokens, keys, or real spec data.
- Change a severity without updating `BREAKING_CHANGES.md` and fixtures.
- Widen scope (orgs, GitHub App, auto-fix, billing) without an ADR.
- Modify `Project Plan.md`.

### Always

- Parameterize SQL and Cypher.
- Verify webhook HMAC over the raw body before decoding.
- Scope every query by `workspace_id`.
- Return `404` for foreign resources.
- Write the outbox row in the same transaction.
- Use UUIDv7 for entities and UUIDv5 for derived graph IDs.
- Sort before hashing or ordering.
- Prefer edges-first: update contract/data docs before code.

---

## 8. PR checklist

- [ ] Description states what, why, how tested, docs updated, risk, rollback.
- [ ] Linked `TASK-###` and `FR-###`.
- [ ] One logical change; no drive-by refactors.
- [ ] Tests demonstrate the behavior; no snapshot-only coverage.
- [ ] Contract/types/schema docs updated.
- [ ] Migration is forward-only and has `down()`.
- [ ] New endpoint has auth, scope, workspace scope, rate limit, contract test.
- [ ] New UI has loading/empty/error/success states and keyboard access.
- [ ] Screenshot or clip for UI changes.
- [ ] CI green.

---

## 9. Known traps

1. **Kuzu locking.** Opening the same directory from two processes fails or corrupts. One writer, one process.
2. **Graph drift.** After a spec upload, the graph is stale until sync completes. Never run impact analysis on a stale graph.
3. **Outbox ≠ queue.** Rows persist until processed. A growing outbox means Redis or the worker is down.
4. **Stream acknowledgements.** Ack after commit, not after reading. A crash before ack is recoverable; a crash after ack is lost work.
5. **Duplicate delivery.** Handlers will run twice in tests and in production. Use unique constraints and state checks.
6. **HMAC over encoded JSON.** Re-encoding changes bytes. Always verify the raw body.
7. **Consumer inference.** Declared > inferred > manual precedence; keep provenance and confidence.
8. **Cycles.** Tarjan first, then order components. Do not let Kahn silently drop nodes.
9. **Canonicalization before diff.** Never diff raw YAML; reordered keys are not changes.
10. **Change fingerprints.** Stable across re-runs; never use array index or random IDs.
11. **Report scope.** A report needs owners; missing `owner_team` is `ERR-1206`, not a blank row.
12. **Upload safety.** No remote `$ref`, no object hydration, no unbounded aliases.
13. **Stale UI polling.** Stop polling on `completed`, `failed`, or unmount.
14. **Timezone.** Store UTC; render UTC with an explicit label.
15. **Generated files.** Regenerate, never patch.

---

## 10. Where to put what

| Change | Update |
|---|---|
| New endpoint | `contracts/openapi.yaml`, `API_CONTRACT.md`, `TASKS.md` if new work |
| New table/column | `DATA_MODEL.md`, migration, model, factory, test |
| New breaking rule | `BREAKING_CHANGES.md`, fixture pair, `engine_version` bump |
| New graph algorithm | `GRAPH_ALGORITHMS.md`, property test, fixture |
| New screen/state | `docs/SCREENS.md`, `DESIGN.md` if a new component |
| New env var | `ENVIRONMENT.md`, `.env.example`, config, `DEPLOYMENT.md` |
| New dependency | PR justification, lockfile, security audit |
| Irreversible decision | ADR in `docs/adr/`, index update |

---

## 11. Escalation format

When blocked, ask with:

```text
Task: TASK-###
Question: one sentence
Options: A / B (trade-off in one line each)
Recommendation: A, because ...
Docs checked: PRD §, API_CONTRACT §
```

Do not ask questions already answered by the linked docs. If a doc is ambiguous, propose the clarification and update it.
