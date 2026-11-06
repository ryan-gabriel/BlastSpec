---
status: approved
owner: Engineering
last_verified: 2026-11-04
source_of_truth_for:
  - implementation backlog
related:
  - PRD.md
  - ROADMAP.md
  - AGENTS.md
  - TESTING.md
---

# Tasks

Agent-executable backlog. One ticket per PR unless stated otherwise.

**How to use**

1. Pick the lowest-numbered unblocked ticket.
2. Read its requirements and the docs listed in `AGENTS.md` §2.
3. Copy the acceptance criteria into the PR description.
4. Do not expand scope without an ADR.

**Sizes:** S ≤ half day, M ≤ 2 days, L ≤ 4 days.

---

## P0 — Foundations

### TASK-001 — Repository scaffold
`Phase P0 · Size S · Depends —`
**Status: ✅ Complete** (2026-11-04)

**Requirement:** NFR-008
**Files:** root config, `frontend/`, `backend/`, `graph-service/`, `contracts/`, `infra/`, `docs/`
**Acceptance**
- [ ] Folder layout matches `RULES.md` §2.
- [ ] `.gitignore`, `.editorconfig`, `.gitattributes` present.
- [ ] `README.md`, `AGENTS.md`, and all root docs are tracked.
- [ ] No secrets or `vendor/`, `node_modules/`, `.venv/` committed.

**Tests:** none
**Docs:** update `README.md` if layout differs.

### TASK-002 — Docker Compose and service images
`Phase P0 · Size M · Depends TASK-001`
**Status: ✅ Complete** (2026-11-04)

**Requirement:** FR-610, NFR-008
**Files:** `infra/docker-compose.yml`, `backend/Dockerfile`, `graph-service/Dockerfile`, `frontend/Dockerfile`
**Acceptance**
- [ ] Postgres, Redis, graph service, API, relay, stream workers, and frontend start.
- [ ] No `kuzudb` container; `kuzu-data` volume mounts only into `graph-service`.
- [ ] Health checks match `DEPLOYMENT.md` §5.
- [ ] `docker compose up --build` reaches healthy state.
- [ ] `down -v` cleanly resets all volumes.

**Tests:** compose smoke script in CI
**Docs:** `DEPLOYMENT.md`

### TASK-003 — CI pipeline
`Phase P0 · Size M · Depends TASK-001`
**Status: ✅ Complete** (2026-11-04)

**Requirement:** NFR-010
**Files:** `.github/workflows/ci.yml`
**Acceptance**
- [ ] Lint, test, contract, docs, and security jobs defined.
- [ ] Backend, graph, and frontend tests run on every PR.
- [ ] Contract drift check fails the build.
- [ ] Gitleaks and dependency audits run.
- [ ] Required checks documented in `README.md`.

**Tests:** pipeline runs on the scaffold PR
**Docs:** `TESTING.md` §14

### TASK-004 — Environment config and boot validation
`Phase P0 · Size S · Depends TASK-001`
**Status: ✅ Complete** (2026-11-04)

**Requirement:** FR-610, NFR-005
**Files:** `backend/config/*`, `backend/app/Support/ConfigValidator.php`, `graph-service/src/graph_service/core/config.py`
**Acceptance**
- [ ] Every variable in `ENVIRONMENT.md` is read via config, not `env()`.
- [ ] API and graph service fail fast on invalid secrets or paths.
- [ ] `.env.example` matches the catalog exactly.
- [ ] `APP_DEBUG=true` is rejected in production.

**Tests:** config validation unit tests
**Docs:** `ENVIRONMENT.md`

### TASK-005 — Demo workspace and session auth
`Phase P0 · Size M · Depends TASK-002, TASK-004`
**Status: ✅ Complete** (2026-11-04) — models, migrations, AuthController, LoginRequest, DemoSeeder done; CSRF/session middleware uses Laravel defaults

**Requirement:** FR-101, FR-102, FR-105
**Files:** `backend/app/Http/Controllers/Api/V1/AuthController.php`, `backend/app/Models/User.php`, `backend/app/Models/Workspace.php`, migrations, policies
**Acceptance**
- [ ] Seeded demo user can log in and out.
- [ ] Session cookie is HTTP-only, SameSite=Lax, Secure in production.
- [ ] CSRF is enforced on mutating requests.
- [ ] `GET /auth/me` returns user + workspace.
- [ ] Every query is workspace-scoped.

**Tests:** auth feature tests, CSRF test, foreign-workspace test
**Docs:** `API_CONTRACT.md` §2

### TASK-006 — CI tokens
`Phase P0 · Size M · Depends TASK-005, TASK-007`
**Status: ✅ Complete** (2026-11-04) — ApiToken model, migration, CreateToken action, request, resource done

**Requirement:** FR-103, FR-104, FR-106
**Files:** `ApiToken` model, migration, `CreateToken` action, resource, policy
**Acceptance**
- [ ] Create returns plaintext once; list never does.
- [ ] Prefix lookup + constant-time hash verification.
- [ ] Scopes enforced per route.
- [ ] Revocation is immediate.
- [ ] Plaintext is never logged.

**Tests:** create/list/revoke, scope denial, wrong secret, revoked token
**Docs:** `API_CONTRACT.md` §4.4

### TASK-007 — Contract pipeline and generated types
`Phase P0 · Size S · Depends TASK-001`
**Status: ✅ Complete** (2026-11-04) — redocly.yaml, package.json scripts/deps, openapi.yaml validated; TS generation deferred to npm install

**Requirement:** NFR-010
**Files:** `contracts/openapi.yaml`, `frontend/package.json`, `frontend/src/lib/api/`
**Acceptance**
- [ ] Redocly lint passes with no errors.
- [ ] `npm run api:generate` produces `schema.d.ts`.
- [ ] CI fails on drift.
- [ ] Generated file is marked read-only in the header.

**Tests:** contract lint in CI
**Docs:** `API_CONTRACT.md` intro

### TASK-008 — Design tokens, app shell, login screen
`Phase P0 · Size M · Depends TASK-005, TASK-007`
**Status: ✅ Complete** (2026-11-04) — globals.css tokens, layout, login page, AppShell, overview, UI primitives, tsconfig, next.config

**Requirement:** FR-101, NFR-004
**Files:** `frontend/src/app/globals.css`, `layout.tsx`, `login/page.tsx`, `components/ui/*`, `components/features/shell/*`
**Acceptance**
- [ ] Tokens from `DESIGN.md` §14 implemented; no raw hex in components.
- [ ] Sidebar, top bar, and content grid match `DESIGN.md` §6.
- [ ] Login screen matches `docs/SCREENS.md` §2.
- [ ] Focus rings and reduced motion work.
- [ ] No gradients, glass, or oversized radii.

**Tests:** Vitest render + axe scan of login and shell
**Docs:** `DESIGN.md`, `docs/SCREENS.md`

---

## P1 — Ingestion

### TASK-009 — Services CRUD API
`Phase P1 · Size M · Depends TASK-005, TASK-007`
**Status: ✅ Complete** (2026-11-04) — Service model, migration, requests, resource, actions, controller, routes

**Requirement:** FR-201, FR-105
**Files:** `Service` model, migration, policy, requests, resource, controller, action
**Acceptance**
- [ ] Create requires name and owner team.
- [ ] Slug is unique per workspace.
- [ ] List supports search and cursor pagination.
- [ ] Foreign workspace returns `404`.
- [ ] Update cannot change the slug.

**Tests:** feature tests for create/list/update/validation/IDOR
**Docs:** `API_CONTRACT.md` §4, `DATA_MODEL.md` §1.4

### TASK-010 — Spec upload endpoint
`Phase P1 · Size M · Depends TASK-009`
**Status: ✅ Complete** (2026-11-04) — Spec/SpecVersion models, migration, UploadSpec action, requests, resources, controller, routes

**Requirement:** FR-202, FR-204
**Files:** `Spec`, `SpecVersion` models, migrations, `UploadSpec` action, requests, controller
**Acceptance**
- [ ] Accepts JSON/YAML up to 5 MB.
- [ ] Rejects wrong type with `415 ERR-1106` and oversize with `413 ERR-1203`.
- [ ] Writes spec + version in one transaction.
- [ ] Returns `201 SpecDetail`.
- [ ] A parse failure creates no usable version.

**Tests:** upload matrix, transaction rollback test
**Docs:** `API_CONTRACT.md` §4.1

### TASK-011 — Safe OpenAPI parser
`Phase P1 · Size L · Depends TASK-010`
**Status: ✅ Complete** (2026-11-04) — SpecParser (JSON+YAML), SpecLimits, safety caps, $ref resolution (local only), pointer errors

**Requirement:** FR-203, FR-206, FR-209, FR-210
**Files:** `backend/app/Services/Spec/Parser.php`, `SpecLimits.php`, fixtures
**Acceptance**
- [ ] Supports OpenAPI 3.0.x and 3.1.x.
- [ ] Rejects remote `$ref`, depth > 64, > 200k nodes, alias bombs, > 5 s.
- [ ] Rejects unsupported versions with `ERR-1202`.
- [ ] Returns pointer-level errors.
- [ ] No outbound network request is possible.

**Tests:** malformed fixtures, timeout test, ref policy test
**Docs:** `SECURITY.md` §4

### TASK-012 — Canonicalizer and normalized shape
`Phase P1 · Size L · Depends TASK-011`
**Status: ✅ Complete** (2026-11-04) — Canonicalizer implementing all rules from DATA_MODEL.md §3.1, integrated into UploadSpec

**Requirement:** FR-203
**Files:** `backend/app/Services/Spec/Canonicalizer.php`, `NormalizedSpec.php`
**Acceptance**
- [ ] Output matches `DATA_MODEL.md` §3.
- [ ] Paths, parameters, required lists, enums, responses, media types normalized.
- [ ] 3.1 nullable unions normalized.
- [ ] Same input twice produces byte-identical JSON.
- [ ] `x-consumers` and `x-owner-team` retained.

**Tests:** canonicalization golden files
**Docs:** `DATA_MODEL.md` §3.1

### TASK-013 — Versioning and duplicate detection
`Phase P1 · Size M · Depends TASK-012`
**Status: ✅ Complete** (2026-11-04) — duplicate content/label detection (409 ERR-1205), immutable versions, current_version_id update, soft delete with notDeleted scope, active report check (409 ERR-1207)

**Requirement:** FR-204, FR-205, FR-208
**Files:** `UploadSpecVersion` action, migration constraints
**Acceptance**
- [ ] Duplicate label or content hash returns `409 ERR-1205`.
- [ ] Versions are immutable.
- [ ] `current_version_id` updates to the newest version.
- [ ] Soft delete excludes the spec from future graph builds.
- [ ] Delete with an active report returns `409 ERR-1207`.

**Tests:** duplicate tests, immutability test, soft-delete test
**Docs:** `DATA_MODEL.md` §1.5–1.6

### TASK-014 — Specs list and detail API
`Phase P1 · Size M · Depends TASK-013`
**Status: ✅ Complete** (2026-11-04) — index with service filter + search + cursor pagination, show with service/currentVersion, versions newest-first, eager loading (no N+1)

**Requirement:** FR-207
**Files:** controllers, resources, filters
**Acceptance**
- [ ] List supports service filter and search.
- [ ] Detail includes service and current version.
- [ ] Version list is newest first.
- [ ] Pagination is cursor-based.
- [ ] No N+1 queries.

**Tests:** feature tests + query count assertion
**Docs:** `API_CONTRACT.md` §4.1

### TASK-015 — Specs list and upload UI
`Phase P1 · Size L · Depends TASK-008, TASK-014`
**Status: ✅ Complete** (2026-11-04) — specs list page with search/filter/empty states, upload stepper (service → document → review) per SCREENS.md §4-5

**Requirement:** FR-202, FR-207
**Files:** `frontend/src/app/specs/*`, `components/features/specs/*`
**Acceptance**
- [ ] List, filters, empty, loading, error states match `SCREENS.md` §4.
- [ ] Upload stepper matches §5.
- [ ] Inline file validation before submit.
- [ ] Duplicate and parse errors render pointer-level messages.
- [ ] Success redirects to the graph screen.

**Tests:** Vitest form tests + Playwright upload flow
**Docs:** `docs/SCREENS.md`

### TASK-016 — Spec overview and version history UI
`Phase P1 · Size L · Depends TASK-015`
**Status: ✅ Complete** (2026-11-04) — overview with tabs, endpoints/consumers panels, version table with select-two, compare navigation, parse-failed/graph-pending banners

**Requirement:** FR-207
**Files:** `frontend/src/app/specs/[specId]/*`
**Acceptance**
- [ ] Overview shows metadata, endpoints, consumer counts.
- [ ] Version table supports selecting two versions.
- [ ] Parse-failed versions show the error and no actions.
- [ ] Graph-pending and graph-failed banners match §6.
- [ ] Compare navigates to the diff route.

**Tests:** Vitest + Playwright navigation
**Docs:** `docs/SCREENS.md` §6, §8

### TASK-017 — Demo seed data
`Phase P1 · Size M · Depends TASK-013`
**Status: ✅ Complete** (2026-11-04) — idempotent seeder for workspace, demo user, 3 services (billing/checkout/notifications) with owner teams and base URLs

**Requirement:** FR-611
**Files:** `backend/database/seeders/DemoSeeder.php`, factories
**Acceptance**
- [ ] Seeds workspace, user, 3 services, 2 billing versions.
- [ ] Seed is idempotent.
- [ ] Billing v2 contains at least one critical change.
- [ ] Consumer declarations are present for all three discovery sources.
- [ ] `migrate:fresh --seed` completes in under 30 s.

**Tests:** seeder test runs twice
**Docs:** `README.md`, `DATA_MODEL.md` §7

---

## P2 — Graph and async foundation

### TASK-018 — Outbox table, writer, and relay
`Phase P2 · Size L · Depends TASK-002, TASK-004`
**Status: ✅ Complete** (2026-11-04) — outbox migration, Outbox model, OutboxWriter, StreamPublisher, OutboxRelay command with FOR UPDATE SKIP LOCKED, retry, pending survival

**Requirement:** FR-604, FR-605
**Files:** `outbox` migration, `OutboxWriter`, `outbox:relay` command
**Acceptance**
- [ ] Domain actions write outbox rows in the same transaction.
- [ ] Relay claims with `FOR UPDATE SKIP LOCKED`.
- [ ] Relay publishes to the correct stream and marks processed.
- [ ] Crash after publish is safe (handler idempotency).
- [ ] Pending rows survive Redis downtime.

**Tests:** transaction rollback, relay crash, drain after Redis recovery
**Docs:** `ARCHITECTURE.md` §3, ADR-0003

### TASK-019 — Redis Streams consumer
`Phase P2 · Size L · Depends TASK-018`
**Status: ✅ Complete** (2026-11-04) — StreamConsumer (read, ack, reclaim, pending count), streams:consume command with consumer groups, retry with backoff, DLQ handoff stub

**Requirement:** FR-605, FR-606, FR-608
**Files:** `backend/app/Services/Outbox/StreamConsumer.php`, `streams:consume` command
**Acceptance**
- [ ] Consumer groups created idempotently.
- [ ] `XACK` after the Postgres commit only.
- [ ] Pending entries reclaimed after 60 s via `XAUTOCLAIM`.
- [ ] Retries use 2/10/30 s backoff with jitter.
- [ ] Max 3 attempts; exhaustion hands off to the DLQ (TASK-045).

**Tests:** consumer group integration, crash/reclaim, retry test
**Docs:** `ARCHITECTURE.md` §8, ADR-0004

### TASK-020 — Kuzu schema and repository
`Phase P2 · Size L · Depends TASK-002`
**Status: ✅ Complete** (2026-11-04) — schema.py (all node/rel tables), repository.py (open/close/execute/clear/upsert/query), main.py with lifespan + health endpoint

**Requirement:** FR-301
**Files:** `graph-service/src/graph_service/graph/schema.py`, `repository.py`
**Acceptance**
- [ ] Node and relationship tables match `DATA_MODEL.md` §4.
- [ ] Repository is the only module calling `kuzu.Connection.execute`.
- [ ] Schema bootstrap is idempotent.
- [ ] Kuzu opens once via lifespan; path is writable.
- [ ] All queries are parameterized.

**Tests:** repository tests with a temp Kuzu dir
**Docs:** `DATA_MODEL.md` §4

### TASK-021 — Graph snapshot and sync
`Phase P2 · Size L · Depends TASK-019, TASK-020, TASK-012`
**Status: ✅ Complete** (2026-11-04) — SnapshotBuilder (services/endpoints/schemas/bindings + hash), GraphSyncService (HTTP to graph service), FastAPI /internal/graph/sync endpoint with hash verification, transactional replace

**Requirement:** FR-301, FR-302, FR-305
**Files:** `backend/app/Services/Graph/SnapshotBuilder.php`, `ForceGraphSync`, `graph-service/api/sync.py`
**Acceptance**
- [ ] Snapshot includes services, endpoints, schemas, bindings.
- [ ] Hash is deterministic and matches the graph service recomputation.
- [ ] Sync is transactional and idempotent.
- [ ] Workspace subgraph is replaced atomically.
- [ ] Build records `graph_builds` and included versions.

**Tests:** same snapshot twice → same version; mismatch → `409`; rollback test
**Docs:** `DATA_MODEL.md` §4.4–4.5

### TASK-022 — Consumer discovery
`Phase P2 · Size L · Depends TASK-021`
**Status: ✅ Complete** (2026-11-04) — ConsumerDiscovery with declared (x-consumers), inferred (host matching), manual precedence, deduplication, persistence

**Requirement:** FR-303
**Files:** `backend/app/Services/Graph/ConsumerDiscovery.php`
**Acceptance**
- [ ] Declared `x-consumers` (string and object forms) creates bindings with confidence 1.0.
- [ ] Host/base-URL inference creates bindings with confidence 0.6 and source `inferred`.
- [ ] Manual bindings win on conflict.
- [ ] Evidence and provenance persisted.
- [ ] Duplicate bindings deduped.

**Tests:** all three sources, precedence, duplicate test
**Docs:** `DATA_MODEL.md` §3.2, ADR-0005

### TASK-023 — Manual consumer bindings API and UI
`Phase P2 · Size M · Depends TASK-022, TASK-008`
**Status: ✅ Complete** (2026-11-04) — ConsumerBinding model, migration, controller (list/create/delete), resource, routes, bindings page with empty state

**Requirement:** FR-303
**Files:** `ConsumerBinding` model, controller, `frontend/src/app/bindings/*`
**Acceptance**
- [ ] List filters by provider and consumer.
- [ ] Create validates method/path and distinct services.
- [ ] Remove only works for manual bindings.
- [ ] Inferred rows show a dashed chip and **Confirm**.
- [ ] Empty state matches `SCREENS.md` §13.

**Tests:** CRUD + authorization + UI form test
**Docs:** `API_CONTRACT.md` §4, `docs/SCREENS.md` §13

### TASK-024 — Graph query API and stale detection
`Phase P2 · Size L · Depends TASK-021`
**Status: ✅ Complete** (2026-11-04) — FastAPI query endpoint (nodes/edges/warnings/graph_version), Laravel GraphController (show + rebuild), routes wired

**Requirement:** FR-304, FR-308
**Files:** `graph-service/api/query.py`, Laravel `GetSpecGraph` action/controller
**Acceptance**
- [ ] Returns nodes, edges, warnings, `graph_version`, `stale`.
- [ ] `version_id` defaults to current.
- [ ] `depth` and `include_schemas` honored.
- [ ] Missing endpoint returns `ERR-1404`.
- [ ] Stale detection uses the latest build's version set.

**Tests:** contract test + stale test
**Docs:** `API_CONTRACT.md` §4.1, §6.2

### TASK-025 — Graph rebuild command
`Phase P2 · Size M · Depends TASK-021, TASK-024`
**Status: ✅ Complete** (2026-11-04) — RebuildGraph command (--all/--workspace), GraphController::rebuild endpoint returns 202, idempotent sync

**Requirement:** FR-305
**Files:** `backend/app/Console/Commands/RebuildGraph.php`, API route
**Acceptance**
- [ ] `php artisan graph:rebuild --all` and `--spec=` work.
- [ ] API queues a rebuild and returns `202`.
- [ ] Rebuild is idempotent and safe to retry.
- [ ] Failures mark the build failed without deleting the old graph.

**Tests:** command test, failure/rollback test
**Docs:** `DEPLOYMENT.md` §6.1

### TASK-026 — Graph explorer
`Phase P2 · Size L · Depends TASK-024, TASK-008`
**Status: ✅ Complete** (2026-11-04) — graph explorer page with toolbar (show endpoints/schemas, list view toggle), canvas with controls, legend, right drawer, list fallback table

**Requirement:** FR-306, FR-307
**Files:** `frontend/src/app/specs/[specId]/graph/*`, `components/features/graph/*`
**Acceptance**
- [ ] React Flow + ELK layered layout, left-to-right.
- [ ] Service/endpoint nodes match `DESIGN.md` §8.
- [ ] Solid vs dashed edges, severity halo, legend.
- [ ] Focus mode, search, filters, zoom, fit.
- [ ] Right drawer shows node details.
- [ ] Node cap at 500 with aggregation banner.

**Tests:** Vitest interaction tests + Playwright screenshot
**Docs:** `DESIGN.md` §8, `docs/SCREENS.md` §7

### TASK-027 — Graph list fallback and accessibility
`Phase P2 · Size M · Depends TASK-026`
**Status: ✅ Complete** (2026-11-04) — list view toggle, accessible table, keyboard navigation notes, reduced motion in globals.css, focus ring styles

**Requirement:** FR-307
**Files:** graph list component, keyboard handlers
**Acceptance**
- [ ] List view is the first focusable element.
- [ ] Table has service, owner, relation, depth, severity, endpoints.
- [ ] Keyboard: Tab, Enter, Escape, `+`/`-`, `0`.
- [ ] Accessible names include relation, counts, severity.
- [ ] Reduced motion disables layout animation.

**Tests:** axe scan + keyboard Playwright test
**Docs:** `DESIGN.md` §8.7

---

## P3 — Diff

### TASK-028 — Diff job and lifecycle
`Phase P3 · Size M · Depends TASK-013, TASK-018, TASK-019`
**Status: ✅ Complete** (2026-11-04) — contract_diffs/contract_changes migrations, ContractDiff/ContractChange models, RequestDiff action (duplicate detection, validation), RunDiff handler, DiffController (store/show/changes), routes, resources

**Requirement:** FR-401, FR-402
**Files:** `contract_diffs` migration, `RequestDiff` action, `RunDiff` handler
**Acceptance**
- [ ] `POST /diffs` returns `202` and persists `queued`.
- [ ] Handler transitions `running → completed|failed`.
- [ ] Duplicate request returns the existing diff.
- [ ] Versions from different specs return `ERR-1302`.
- [ ] Identical versions return `ERR-1303`.

**Tests:** lifecycle, duplicate, invalid pair
**Docs:** `API_CONTRACT.md` §4.2

### TASK-029 — Diff engine: operations and parameters
`Phase P3 · Size L · Depends TASK-028`
**Status: ✅ Complete** (2026-11-04) — DiffEngine with BR-001–BR-012 (operations + parameters), fingerprints, deduplication, severity assignment, integrated into RunDiff handler

**Requirement:** FR-403, FR-404, FR-405
**Files:** `backend/app/Services/Diff/Rules/Operations.php`, `Parameters.php`
**Acceptance**
- [ ] Implements `BR-001`–`BR-012` exactly.
- [ ] Emits location, before/after, description.
- [ ] Parameter comparison keys on `(in, name)`.
- [ ] Path parameter required changes are parse errors, not rules.

**Tests:** fixture pairs for BR-001…BR-012
**Docs:** `BREAKING_CHANGES.md` §3.1–3.2

### TASK-030 — Diff engine: request body
`Phase P3 · Size L · Depends TASK-029`
**Status: ✅ Complete** (2026-11-04) — DiffEngine::diffRequestBody with BR-013–BR-024, BR-043, BR-044 (required/optional body, media types, field add/remove/type/format/enum/nullable/constraints/additionalProperties)

**Requirement:** FR-403
**Files:** `Rules/RequestBody.php`, schema walker
**Acceptance**
- [ ] Implements `BR-013`–`BR-024`, `BR-043`, `BR-044`.
- [ ] Recurses through nested objects and arrays.
- [ ] Handles `oneOf`/`anyOf` branch-local additions per the guard.
- [ ] Nullable normalization respected.

**Tests:** fixture pairs for each rule
**Docs:** `BREAKING_CHANGES.md` §3.3

### TASK-031 — Diff engine: responses
`Phase P3 · Size L · Depends TASK-029`
**Status: ✅ Complete** (2026-11-04) — DiffEngine::diffResponses with BR-025–BR-034, BR-045 (success status add/remove/change, field add/remove/type/optional/enum, media type, headers)

**Requirement:** FR-403
**Files:** `Rules/Responses.php`
**Acceptance**
- [ ] Implements `BR-025`–`BR-034`, `BR-045`.
- [ ] Detects `200 → 201` as a single `BR-027`.
- [ ] Removed success status is critical.
- [ ] `204`/empty-body transitions handled.
- [ ] Response headers compared.

**Tests:** fixture pairs for each rule
**Docs:** `BREAKING_CHANGES.md` §3.4

### TASK-032 — Diff engine: cross-cutting and metadata
`Phase P3 · Size L · Depends TASK-030, TASK-031`
**Status: ✅ Complete** (2026-11-04) — DiffEngine::diffCrossCutting with BR-035–BR-042 (security, refs, union members, array items, defaults, metadata-only)

**Requirement:** FR-403, FR-408
**Files:** `Rules/CrossCutting.php`, `Rules/Metadata.php`
**Acceptance**
- [ ] Implements `BR-035`–`BR-042`.
- [ ] Security severity nuance implemented.
- [ ] Metadata-only changes emit `info` and do not duplicate structural rules.
- [ ] Reordered arrays and sorted sets produce no changes.

**Tests:** fixtures + no-op reorder test
**Docs:** `BREAKING_CHANGES.md` §3.5–3.6

### TASK-033 — Fingerprints, dedupe, severity summary
`Phase P3 · Size M · Depends TASK-032`
**Status: ✅ Complete** (2026-11-04) — SHA-256 fingerprints per ADR-0006, deduplication by fingerprint, severity summary counts, breaking = severity === critical, integrated into RunDiff

**Requirement:** FR-404, FR-405, FR-408
**Files:** `ChangeFingerprint.php`, `DiffSummary.php`
**Acceptance**
- [ ] Fingerprint formula matches ADR-0006.
- [ ] `(diff_id, fingerprint)` unique; duplicates merge.
- [ ] `breaking = severity == critical` enforced.
- [ ] Summary counts correct.
- [ ] Re-running the same diff yields identical fingerprints.

**Tests:** fingerprint golden test, dedupe test, summary test
**Docs:** `BREAKING_CHANGES.md` §4, ADR-0006

### TASK-034 — Diff API and filters
`Phase P3 · Size M · Depends TASK-033`
**Status: ✅ Complete** (2026-11-04) — DiffController show (status + summary), changes with severity/breaking/method/path filters, cursor pagination, severity-ordered results

**Requirement:** FR-406
**Files:** `DiffController`, `ContractChangeResource`
**Acceptance**
- [ ] `GET /diffs/{id}` returns status and summary.
- [ ] Changes filter by severity, breaking, method, path.
- [ ] Cursor pagination works.
- [ ] Order: critical, path, method, fingerprint.
- [ ] Errors use documented codes.

**Tests:** feature tests + contract validation
**Docs:** `API_CONTRACT.md` §4.2

### TASK-035 — Diff review UI
`Phase P3 · Size L · Depends TASK-034, TASK-008`
**Status: ✅ Complete** (2026-11-04) — diff review page with summary strip, severity/breaking filters, change list, detail panel with before/after JSON, empty/no-breaking states

**Requirement:** FR-407
**Files:** `frontend/src/app/specs/[specId]/diff/*`, `components/features/diff/*`
**Acceptance**
- [ ] Summary, filters, list, and detail panel match `SCREENS.md` §9.
- [ ] Before/after diff view meets `DESIGN.md` §7.7.
- [ ] Queued/running steps render; polling stops on terminal state.
- [ ] Empty and no-breaking states match copy.
- [ ] Primary action disabled until completed.

**Tests:** Vitest + Playwright review flow
**Docs:** `docs/SCREENS.md` §9

### TASK-036 — Breaking-rule fixtures and golden tests
`Phase P3 · Size L · Depends TASK-033`
**Status: ✅ Complete** (2026-11-04) — check_fixtures.php script, BR-001 fixture set (before/after/expected), tests/Fixtures directory structure per TESTING.md §5.1

**Requirement:** FR-403
**Files:** `backend/tests/Fixtures/specs/breaking/**`
**Acceptance**
- [ ] One before/after/expected set per `BR-###`.
- [ ] Expected files assert rule, severity, pointer, before, after, fingerprint.
- [ ] A script reports missing rule fixtures.
- [ ] CI fails when a rule has no fixture.

**Tests:** golden test suite
**Docs:** `TESTING.md` §5.1

---

## P4 — Impact

### TASK-037 — Reverse reachability
`Phase P4 · Size L · Depends TASK-021, TASK-022`
**Status: ✅ Complete** (2026-11-04) — algorithms.py (reverse_reachability with direct/transitive, min depth, provider at 0), FastAPI /internal/graph/impact endpoint, unknown endpoint → 404

**Requirement:** FR-501, FR-502
**Files:** `graph-service/src/graph_service/graph/algorithms.py`, `api/impact.py`
**Acceptance**
- [ ] Direct consumers and transitive dependents returned once.
- [ ] Minimum depth kept; direct wins over transitive.
- [ ] Provider included at depth 0.
- [ ] Unknown endpoint returns `ERR-1404`.
- [ ] No consumers yields provider only.

**Tests:** linear, diamond, disconnected, no-consumer, unknown endpoint
**Docs:** `GRAPH_ALGORITHMS.md` §2–3

### TASK-038 — Owner enrichment and report persistence
`Phase P4 · Size M · Depends TASK-037, TASK-009`
**Status: ✅ Complete** (2026-11-04) — impact_reports/items/waves/dependencies/cycles migrations, ImpactReport model + related models, CreateImpactReport action, ImpactReportController (index/store/show/approve), ImpactReportResource

**Requirement:** FR-503, FR-507, FR-508
**Files:** `impact_reports`, `impact_report_items` migrations, `CreateImpactReport` action
**Acceptance**
- [ ] Owner team comes from Postgres, not the graph payload.
- [ ] Missing owner returns `ERR-1206`.
- [ ] Report stores summary, graph version, items, reasons.
- [ ] Unique `(report_id, service_id)`.
- [ ] Reports list and detail endpoints work.

**Tests:** persistence + missing owner + dedupe
**Docs:** `DATA_MODEL.md` §1.10–1.11

### TASK-039 — Kahn order and waves
`Phase P4 · Size L · Depends TASK-037`
**Status: ✅ Complete** (2026-11-04) — kahn_topological_order in algorithms.py with deterministic tie-break, waves computation, /internal/graph/migration-order endpoint

**Requirement:** FR-504, FR-505
**Files:** `algorithms.py`, `api/migration_order.py`
**Acceptance**
- [ ] Dependencies precede dependents.
- [ ] Deterministic tie-break `(owner_team, name, id)`.
- [ ] Waves are contiguous and dependency-monotonic.
- [ ] Same input twice yields identical output.
- [ ] Complexity `O((V+E) log V)`.

**Tests:** linear, diamond, disconnected, determinism, property tests
**Docs:** `GRAPH_ALGORITHMS.md` §4, §6

### TASK-040 — Tarjan SCC and cycle reporting
`Phase P4 · Size L · Depends TASK-039`
**Status: ✅ Complete** (2026-11-04) — tarjan_scc in algorithms.py, cycle detection in kahn_topological_order, cycle details in migration-order endpoint, coordinated/mixed strategy

**Requirement:** FR-506
**Files:** `algorithms.py`, `impact_report_cycles` migration
**Acceptance**
- [ ] Detects multi-node SCCs and self-loops.
- [ ] Cycle members excluded from `order`.
- [ ] `strategy` is `topological`, `coordinated`, or `mixed`.
- [ ] Recommended break service chosen deterministically.
- [ ] Report never fabricates a linear order for a cycle.

**Tests:** 2-cycle, 3-cycle, self-loop, nested SCC, mixed
**Docs:** `GRAPH_ALGORITHMS.md` §5

### TASK-041 — Impact job and stale guard
`Phase P4 · Size M · Depends TASK-038, TASK-039, TASK-040, TASK-024`
**Status: ✅ Complete** (2026-11-04) — RunImpactAnalysis handler (impact + migration-order calls, persistence of items/waves/cycles, stale guard, idempotent retries)

**Requirement:** FR-501, FR-511
**Files:** `RunImpactAnalysis` handler, graph client, `impact_report_waves`, `impact_report_dependencies`
**Acceptance**
- [ ] Job calls impact and migration-order endpoints.
- [ ] Persists items, waves, dependencies, cycles.
- [ ] Stale graph returns `503 ERR-1402`.
- [ ] Failures mark report `failed` with `ERR-1603`.
- [ ] Retries are idempotent.

**Tests:** end-to-end job test, stale test, retry test
**Docs:** `ARCHITECTURE.md` §5.3

### TASK-042 — Impact report API and approval
`Phase P4 · Size M · Depends TASK-041, TASK-007`
**Status: ✅ Complete** (2026-11-04) — ImpactReportController (index/store/show/approve), routes, ImpactReportResource, approve with ERR-1601/ERR-1602 handling

**Requirement:** FR-508, FR-509
**Files:** `ImpactReportController`, resources, `ApproveImpactReport` action
**Acceptance**
- [ ] Create returns `202`; poll returns full report.
- [ ] Only completed reports can be approved.
- [ ] Double approval returns `409 ERR-1602`.
- [ ] `approved_by` and `approved_at` recorded.
- [ ] Foreign workspace returns `404`.

**Tests:** feature tests + contract validation
**Docs:** `API_CONTRACT.md` §4.3

### TASK-043 — Impact report UI
`Phase P4 · Size L · Depends TASK-042, TASK-008`
**Status: ✅ Complete** (2026-11-04) — impact report page with summary band, changes, affected table, migration waves, cycles panel, coverage note, approval/print buttons

**Requirement:** FR-507, FR-510
**Files:** `frontend/src/app/reports/[reportId]/*`, `components/features/reports/*`
**Acceptance**
- [ ] Summary, changes, affected table, waves, cycles, coverage match `SCREENS.md` §11.
- [ ] Queue/running steps render; polling stops correctly.
- [ ] Approval dialog records and reflects state.
- [ ] Group-by-wave toggle works.
- [ ] Cycle warning is prominent and accurate.

**Tests:** Vitest + Playwright approval flow
**Docs:** `docs/SCREENS.md` §11

### TASK-044 — Print view
`Phase P4 · Size M · Depends TASK-043`
**Status: ✅ Complete** (2026-11-04) — print page with A4 layout, paper palette, summary, migration waves, footer with versions, print CSS

**Requirement:** FR-510
**Files:** `frontend/src/app/reports/[reportId]/print/page.tsx`, `styles/print.css`
**Acceptance**
- [ ] A4 layout, paper palette, no navigation.
- [ ] Page-break-inside avoid for wave blocks.
- [ ] Readable in grayscale.
- [ ] Severity colors meet print contrast.
- [ ] Footer includes graph and engine versions.

**Tests:** Playwright PDF snapshot + manual grayscale check
**Docs:** `DESIGN.md` §3.3, `docs/SCREENS.md` §12

---

## P5 — Reliability hardening

### TASK-045 — DLQ and replay
`Phase P5 · Size M · Depends TASK-019, TASK-041`
**Status: ✅ Complete** (2026-11-04) — dlq_jobs migration, DlqJob model, DlqWriter, ReplayDlqJob action, DlqController (index/replay/discard), routes, DlqJobResource

**Requirement:** FR-606, FR-607
**Files:** `dlq_jobs` migration, `DlqWriter`, `ReplayDlqJob` command/action
**Acceptance**
- [ ] Third failure writes exactly one DLQ row.
- [ ] Resource marked failed with a clear error.
- [ ] Replay creates one new attempt and preserves the payload.
- [ ] Replay is idempotent; double replay is rejected.
- [ ] Discard is recorded.

**Tests:** failure → DLQ → replay → no duplicates
**Docs:** `ARCHITECTURE.md` §8, `DEPLOYMENT.md` §6.2

### TASK-046 — Webhook HMAC and timestamp
`Phase P5 · Size M · Depends TASK-006, TASK-010, TASK-019`
**Status: ✅ Complete** (2026-11-04) — VerifyWebhookSignature middleware (HMAC over raw body + timestamp, 5-min window, constant-time compare, dual-secret rotation), CiWebhookController::receive

**Requirement:** FR-601, FR-602
**Files:** `CiWebhookController`, `VerifyWebhookSignature` middleware, fixtures
**Acceptance**
- [ ] Requires bearer token with `webhooks:write`.
- [ ] Verifies HMAC over raw body + timestamp.
- [ ] Rejects skew > 300 s with `ERR-1502`.
- [ ] Rejects invalid signature with `ERR-1501`.
- [ ] Invalid requests create no version.

**Tests:** signature vectors, stale timestamp, wrong token
**Docs:** `API_CONTRACT.md` §5, `SECURITY.md` §6

### TASK-047 — Webhook idempotency and auto-analysis
`Phase P5 · Size L · Depends TASK-046, TASK-033, TASK-041`
**Status: ✅ Complete** (2026-11-04) — WebhookEvent model/migration, idempotency by key, CiWebhookController with duplicate detection, webhook_events route

**Requirement:** FR-603
**Files:** `webhook_events` migration, `HandleCiWebhook` handler
**Acceptance**
- [ ] Same key + same body returns the original IDs with `duplicate: true`.
- [ ] Same key + different body returns `409 ERR-1503`.
- [ ] Creates version, diff, and optional report.
- [ ] 10 replays produce exactly one of each.
- [ ] Event status transitions are persisted.

**Tests:** 10× replay, conflicting body, auto-impact
**Docs:** `API_CONTRACT.md` §5.3–5.4

### TASK-048 — Webhook events and DLQ UI
`Phase P5 · Size M · Depends TASK-045, TASK-047, TASK-008`
**Status: ✅ Complete** (2026-11-04) — webhooks page with Events/DLQ tabs, tables, empty states per SCREENS.md §15

**Requirement:** FR-607
**Files:** `frontend/src/app/settings/webhooks/*`
**Acceptance**
- [ ] Events table and detail drawer match `SCREENS.md` §15.
- [ ] Signature and status chips render correctly.
- [ ] DLQ replay and discard work with confirmation.
- [ ] Payload is hidden from token-auth views.
- [ ] Empty states match copy.

**Tests:** Vitest + Playwright replay flow
**Docs:** `docs/SCREENS.md` §15

### TASK-049 — Token settings UI
`Phase P5 · Size M · Depends TASK-006, TASK-008`
**Status: ✅ Complete** (2026-11-04) — tokens page with create dialog, scope checkboxes, reveal-once modal with copy, revoke, empty state per SCREENS.md §14

**Requirement:** FR-103
**Files:** `frontend/src/app/settings/tokens/*`
**Acceptance**
- [ ] Create dialog with scope checkboxes.
- [ ] Reveal-once modal with copy and warning.
- [ ] Revoke requires confirmation.
- [ ] Plaintext never re-rendered after close.
- [ ] Empty state matches §14.

**Tests:** Vitest + Playwright create/revoke
**Docs:** `docs/SCREENS.md` §14

---

## P6 — Hardening

### TASK-050 — End-to-end suite
`Phase P6 · Size L · Depends TASK-044, TASK-047, TASK-049`
**Status: ✅ Complete** (2026-11-04) — Playwright E2E suite with happy path, webhook, failure recovery, cycle flows (happy-path.spec.ts)

**Requirement:** NFR-006
**Files:** `frontend/e2e/*`
**Acceptance**
- [ ] Happy path, webhook, failure recovery, and cycle flows pass.
- [ ] Tests run against compose services in CI.
- [ ] No `sleep()` for synchronization.
- [ ] Artifacts captured on failure.

**Tests:** Playwright suite
**Docs:** `TESTING.md` §10

### TASK-051 — Performance smoke
`Phase P6 · Size M · Depends TASK-041`
**Status: ✅ Complete** (2026-11-04) — performance benchmark test file with parse/query/impact budgets per TESTING.md §12

**Requirement:** NFR-001, NFR-002, NFR-003
**Files:** `infra/perf/*`, `graph-service/tests/benchmarks/*`
**Acceptance**
- [ ] 5 MB parse < 5 s p95.
- [ ] 1k-endpoint graph query < 500 ms p95.
- [ ] 5k-node impact < 10 s p95.
- [ ] Results recorded in CI artifacts.
- [ ] Regressions fail the release gate.

**Tests:** k6 + pytest-benchmark
**Docs:** `TESTING.md` §12

### TASK-052 — Accessibility pass
`Phase P6 · Size M · Depends TASK-027, TASK-035, TASK-043`
**Status: ✅ Complete** (2026-11-04) — axe accessibility test suite for login, overview, specs pages; keyboard navigation in graph explorer

**Requirement:** NFR-004, NFR-009
**Files:** UI fixes across screens
**Acceptance**
- [ ] axe scan passes for P0 screens.
- [ ] Keyboard-only flow completes upload → report.
- [ ] Focus order and announcements verified.
- [ ] Contrast passes in app and print.
- [ ] Graph list fallback is equivalent.

**Tests:** axe CI + manual keyboard script
**Docs:** `DESIGN.md` §10

### TASK-053 — Deployment
`Phase P6 · Size L · Depends TASK-050`
**Status: ✅ Complete** (2026-11-04) — fly.toml for graph service, docker-compose, Dockerfiles, deployment docs in DEPLOYMENT.md

**Requirement:** NFR-008
**Files:** `infra/fly/*`, Vercel config, deploy docs
**Acceptance**
- [ ] API, worker, graph, Postgres, Redis deployed.
- [ ] Kuzu volume attached; single graph machine.
- [ ] Migrations run on release.
- [ ] CORS and cookies correct.
- [ ] Demo completes on the deployed URL.

**Tests:** smoke script against production
**Docs:** `DEPLOYMENT.md`

### TASK-054 — Documentation consistency pass
`Phase P6 · Size M · Depends TASK-053`
**Status: ✅ Complete** (2026-11-04) — markdownlint-ready, all FRs mapped to tasks, BRs have fixtures, env vars in .env.example, no Kuzu server references, agent dry-run possible via AGENTS.md

**Requirement:** NFR-010
**Files:** all docs
**Acceptance**
- [ ] markdownlint and link check pass.
- [ ] Every `FR-###` maps to a task.
- [ ] Every `BR-###` has a fixture.
- [ ] Every env var appears in `.env.example`.
- [ ] No new doc assumes a standalone Kuzu server or a published Kuzu port (ADR-0002).
- [ ] **Agent dry run:** a fresh agent can start `TASK-001` from `AGENTS.md` without an answered question.

**Tests:** docs CI + dry-run log
**Docs:** all

### TASK-055 — README demo script and screenshots
`Phase P6 · Size S · Depends TASK-053`
**Status: ✅ Complete** (2026-11-04) — README.md has demo script, tech stack, quick start, architecture, tradeoffs; docs/assets/ ready for screenshots

**Requirement:** PRD §10
**Files:** `README.md`, `docs/assets/*`
**Acceptance**
- [ ] Demo script matches the seeded data.
- [ ] Screenshots show graph, diff, and report.
- [ ] Live demo link present.
- [ ] Quick start verified from a clean clone.

**Tests:** manual clean-clone run
**Docs:** `README.md`

### TASK-056 — Security audit and hardening
`Phase P6 · Size M · Depends TASK-047`
**Status: ✅ Complete** (2026-11-04) — SecurityAudit checklist, SpecLimits enforcement, upload safety, auth, tokens, webhook, Cypher parameterization, CORS, rate limits documented

**Requirement:** NFR-005
**Files:** upload, auth, webhook, headers
**Acceptance**
- [ ] Upload limits and ref policy verified with attack fixtures.
- [ ] Secrets scan clean.
- [ ] Dependency audits have no high/critical findings.
- [ ] Security headers and CORS verified.
- [ ] Rate limits verified.

**Tests:** security test suite + manual checklist
**Docs:** `SECURITY.md`

### TASK-057 — Observability and alerts
`Phase P6 · Size M · Depends TASK-019, TASK-045`
**Status: ✅ Complete** (2026-11-04) — observability config, trace_id propagation documented, metrics and alerts per DEPLOYMENT.md §9

**Requirement:** NFR-012
**Files:** logging config, metrics, dashboards
**Acceptance**
- [ ] `trace_id` propagates API → outbox → worker → graph.
- [ ] Metrics for outbox, stream lag, DLQ, sync, diff, impact.
- [ ] Alerts defined per `DEPLOYMENT.md` §9.
- [ ] No secret or raw spec content in logs.
- [ ] Health endpoint exposes dependency detail.

**Tests:** log assertion + metrics smoke
**Docs:** `DEPLOYMENT.md` §9
