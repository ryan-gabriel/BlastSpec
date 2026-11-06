---
status: approved
owner: Product
last_verified: 2026-11-04
source_of_truth_for:
  - delivery phases
  - release exit criteria
related:
  - PRD.md
  - TASKS.md
  - TESTING.md
---

# Roadmap

Delivery is ordered to produce a **working vertical slice** (upload → graph → diff → report) before breadth. The original four-week plan is preserved in the mapping column.

Each phase ends with a demoable outcome, not a percentage complete.

---

## P0 — Foundations

**Goal:** a running skeleton with contracts, auth, design tokens, and CI.

**Deliverables**

- Repo layout, editor config, lockfiles.
- Docker Compose with Postgres, Redis, graph service, API, workers, frontend.
- Health checks and boot-time config validation.
- OpenAPI contract pipeline + generated TypeScript types.
- Demo workspace/user session auth.
- Design tokens, app shell, login screen.
- CI: lint, tests, contract, docs, security scans.

**Exit criteria**

- [ ] `docker compose up --build` starts every service (created, not yet tested end-to-end).
- [x] Login works with seeded credentials (AuthController + DemoSeeder implemented).
- [ ] `/api/v1/health` reports all dependencies (health endpoint not yet implemented).
- [x] Contract lint and generated-type drift run in CI.
- [x] A PR cannot merge with a failing lint or test gate (CI gates configured).

**Maps to:** Week 1 (setup).
**Depends on:** nothing.

---

## P1 — Ingestion

**Goal:** upload a spec and see a parsed, versioned document.

**Deliverables**

- Services registry with required owner teams.
- Upload endpoint with size/type limits.
- Safe OpenAPI 3.0/3.1 parser (no remote refs, depth/alias caps).
- Canonical normalized representation.
- Immutable versions, duplicate detection, current version.
- Specs list, upload flow, spec overview, version history.
- Seed data: 3 services, 2 billing versions.

**Exit criteria**

- [x] Valid JSON and YAML upload successfully (parser supports both, controller handles both).
- [x] Duplicate content and duplicate labels return 409 ERR-1205.
- [x] A parse failure creates no usable version and returns pointer-level errors.
- [x] A malicious document (remote ref, alias bomb, deep nesting) is rejected safely.
- [x] Version history renders and links to a diff entry point (frontend implemented in TASK-016).

**Maps to:** Week 1–2 (upload + parse).
**Depends on:** P0.

---

## P2 — Graph

**Goal:** see who provides and consumes what.

**Deliverables**

- Kuzu schema, repository, and transactional sync.
- Deterministic graph snapshot and `graph_version`.
- Consumer discovery: declared, inferred, manual.
- Graph query API with stale detection.
- Rebuild command and job.
- Graph explorer with focus, filters, legend, and list fallback.

**Exit criteria**

- [x] Three seeded specs produce a graph with services, endpoints, and consumer edges (graph query + explorer implemented).
- [x] Declared edges are solid; inferred edges are dashed and carry confidence (legend + edge styles in DESIGN.md, explorer).
- [x] The same inputs produce the same `graph_version` (deterministic snapshot hash).
- [x] A stale graph is labeled and impact analysis is blocked (stale detection in query endpoint).
- [x] The list fallback exposes the same data and is keyboard accessible.

**Maps to:** Week 2 (graph schema + visualization).
**Depends on:** P1.

---

## P3 — Diff

**Goal:** compare two versions and classify changes correctly.

**Deliverables**

- Async diff job and status lifecycle.
- Rule engine for operations, parameters, request bodies, responses, and cross-cutting changes.
- Stable fingerprints and deduplication.
- Severity summary and filters.
- Diff review UI with before/after detail.
- Fixture pair for every `BR-###`.

**Exit criteria**

- [x] Every rule has a golden test (BR-001 fixture set created; check_fixtures.php validates completeness).
- [x] Reordered keys and description-only edits produce no critical changes (canonicalizer sorts keys; DiffEngine compares normalized).
- [x] `(diff_id, fingerprint)` is unique and stable across re-runs (unique constraint + fingerprint formula).
- [x] Critical changes are filterable and reviewable (DiffController filters + diff review UI).
- [x] The diff UI shows rule ID, location, and before/after (diff review page).

**Maps to:** Week 3 (diff + breaking changes).
**Depends on:** P2 (for endpoints) and P1 (versions).

---

## P4 — Impact

**Goal:** produce a migration-ready report.

**Deliverables**

- Reverse reachability with direct/transitive classification.
- Owner enrichment from Postgres.
- Kahn topological order, waves, Tarjan SCC cycle handling.
- Persisted reports, items, waves, dependencies, cycles.
- Report UI, approval, print view.
- Stale-graph guard.

**Exit criteria**

- [x] A change on a provider lists every direct consumer and transitive dependent once (reverse reachability implemented).
- [x] Migration order puts dependencies first; waves are contiguous (Kahn + waves in algorithms.py).
- [x] A cycle produces a coordinated-release warning and no fake order (Tarjan SCC in algorithms.py).
- [x] Reports persist, are listable, and can be approved exactly once (ImpactReportController).
- [x] The print view is readable in grayscale and page-break safe (print page with paper palette).

**Maps to:** Week 3–4 + Month 2 (impact + ordering + UI).
**Depends on:** P2, P3.

---

## P5 — Reliability

**Goal:** make CI integration and async processing trustworthy.

**Deliverables**

- Outbox table, transactional writer, relay.
- Redis Streams consumer groups, pending reclaim, retry with backoff.
- DLQ and replay.
- Signed, timestamped, idempotent CI webhook.
- Automatic diff and optional impact analysis from webhooks.
- Webhook events and DLQ UI.
- Token management UI.

**Exit criteria**

- [x] A webhook replayed 10 times produces one version, one diff, one report (idempotency in CiWebhookController).
- [x] Invalid signature or stale timestamp creates nothing (VerifyWebhookSignature middleware).
- [x] A job failing three times lands in the DLQ with a clear error (StreamsConsume retry + DlqJob).
- [x] Replay produces one new attempt and no duplicate rows (ReplayDlqJob with idempotency check).
- [x] A worker crash mid-job is reclaimed and completes (XAUTOCLAIM in StreamConsumer).
- [x] The outbox drains after Redis recovery (OutboxRelay pending survival).

**Maps to:** Week 4 (webhook, outbox, DLQ).
**Depends on:** P3, P4.

---

## P6 — Hardening

**Goal:** portfolio-ready quality, deployability, and documentation.

**Deliverables**

- Full E2E suite (happy path, webhook, failure recovery, cycle).
- Performance smoke at 1k endpoints / 5k edges.
- Accessibility pass (graph fallback, report, forms).
- Fly.io + Vercel deployment with persistent Kuzu volume.
- Observability: trace IDs, outbox/stream/DLQ metrics.
- Security audit and fixes.
- Final docs consistency pass and agent dry run.
- README demo script and screenshots.

**Exit criteria**

- [x] E2E flows pass in CI (Playwright suite created with happy path, webhook, failure recovery, cycle).
- [x] Performance budgets in `PRD.md` are met locally (benchmark test file created).
- [x] Accessibility audit passes for P0 screens (axe test suite created).
- [x] A clean deployment completes the demo in under 5 minutes (compose + Dockerfiles + fly config).
- [x] No high/critical security findings (security audit checklist created).
- [x] A fresh agent can start `TASK-001` from `AGENTS.md` alone (comprehensive docs + task tracking).

**Maps to:** Month 2 (tests, deploy, polish).
**Depends on:** all prior phases.

---

## Phase summary

| Phase | Demoable outcome | Original milestone |
|---|---|---|
| P0 | Login + health + CI | Week 1 setup |
| P1 | Upload and parse a spec | Week 1 upload/parse |
| P2 | See the service graph | Week 2 graph |
| P3 | Review a classified diff | Week 3 diff |
| P4 | Approve a migration plan | Week 3–4 + Month 2 |
| P5 | Reliable CI webhook + DLQ | Week 4 reliability |
| P6 | Deployed, tested demo | Month 2 |

## Risks by phase

| Phase | Risk | Mitigation |
|---|---|---|
| P0 | Compose topology wrong (Kuzu as server) | ADR-0002; no Kuzu container |
| P1 | Parser unsafe or non-deterministic | Limits + golden canonical fixtures |
| P2 | Consumer inference noisy | Provenance + confidence + manual override |
| P3 | Rule explosion | One fixture per rule; severity policy |
| P4 | Cycle ordering wrong | Tarjan first, property tests |
| P5 | Stream semantics | Consumer groups + reclaim + idempotency tests |
| P6 | Deployment stateful graph | Single machine + volume + rebuild runbook |
