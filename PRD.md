---
status: approved
owner: Product
last_verified: 2026-11-02
source_of_truth_for:
  - product behavior
  - MVP scope
  - acceptance criteria
related:
  - GLOSSARY.md
  - ARCHITECTURE.md
  - API_CONTRACT.md
  - DESIGN.md
  - TASKS.md
---

# Product Requirements Document — BlastSpec

**Product:** BlastSpec — API Contract Change Impact Analyzer
**Version:** 1.0 (MVP)
**Date:** 2026-11-02

---

## 1. Summary

BlastSpec ingests OpenAPI specs, builds a service-dependency graph, detects breaking contract changes between versions, and produces a migration-ready impact report: which services are affected, which teams to notify, and in what order to migrate.

The product demonstrates platform-engineering capability: graph modeling, reverse reachability, topological ordering with cycle handling, asynchronous processing, transactional outbox, and dead-letter recovery.

## 2. Problem

API contracts drift silently. A provider removes a field or adds a required parameter; consumers discover it in production. Existing tools lint a spec in isolation but do not answer:

1. **Who consumes this endpoint?**
2. **What breaks if this changes?**
3. **Who must be notified?**
4. **In what order do we migrate?**

Without a dependency model, teams rely on tribal knowledge and spreadsheets. BlastSpec makes the blast radius explicit before deployment.

## 3. Personas

| Persona | Need | Success looks like |
|---|---|---|
| **Platform engineer** (primary) | See cross-service impact before approving a contract change | A report listing affected services, owners, and migration waves in under a minute |
| **Service owner** | Know whether a provider change breaks their consumers | A clear list of critical changes with endpoints and schema pointers |
| **Engineering manager** | Coordinate a migration across teams | Owners grouped by wave, printable report, approval record |
| **CI pipeline** (non-human) | Fail or annotate a build when a contract breaks | Signed webhook returns `202`, is idempotent, and links to a report |

## 4. Jobs to be done

- When I upload our API specs, I want a dependency graph so I can see who talks to whom.
- When I change an endpoint, I want to know which consumers break before I merge.
- When a change is breaking, I want the owning teams and a migration order so I can coordinate.
- When CI publishes a new spec, I want impact analysis to run automatically and reliably.
- When analysis fails, I want to see why and replay it without duplicating work.

## 5. Scope

### 5.1 In scope (MVP)

- Single seeded demo workspace; session login for the UI.
- Hashed CI tokens with scopes.
- Service registry with owner teams.
- OpenAPI 3.0.x and 3.1.x upload (JSON/YAML), versioning, parsing, canonicalization.
- Graph of services, endpoints, schema components, and consumer bindings.
- Consumer discovery via explicit `x-consumers`, base-URL/host matching, and manual declaration.
- Diff between any two versions of a spec.
- Detection of breaking, warning, and informational changes per `BREAKING_CHANGES.md`.
- Impact analysis with reverse reachability, owner lookup, topological migration order, waves, and cycle reporting.
- Persisted impact reports with approval.
- CI webhook with HMAC, timestamp validation, and idempotency.
- Transactional outbox, Redis Streams processing, retry, DLQ, and replay.
- Interactive graph explorer with an accessible list fallback.
- Demo seed data: 3 services, 2 spec versions, one breaking change, one report.

### 5.2 Out of scope (MVP)

| Excluded | Reason |
|---|---|
| Full OpenAPI linting/validation (Spectral rules) | Use existing tools; BlastSpec consumes valid specs |
| Auto-remediation or code generation from diffs | Detection and coordination only |
| GitHub App / repository crawling | Webhook + upload cover the demo |
| Organization and team management UI | Single demo workspace |
| Billing, SSO, SCIM | Not relevant to the portfolio signal |
| Real-time collaborative editing | Read/analyze product |
| AsyncAPI, GraphQL, gRPC contracts | OpenAPI only |
| Automatic backward-compatible schema evolution | Explicit non-goal; BlastSpec reports, it does not transform |
| Production-grade multi-region deployment | Free-tier demo target |

## 6. Functional requirements

Priority: **P0** = MVP release blocker, **P1** = should ship, **P2** = later.

### 6.1 Workspace and access

| ID | Requirement | Priority |
|---|---|---|
| FR-101 | The app MUST provide a seeded demo user and session login. Registration is not exposed. | P0 |
| FR-102 | The UI MUST use an HTTP-only session cookie and CSRF protection for mutating requests. | P0 |
| FR-103 | A user MUST be able to create a CI token with a name and scopes; plaintext is shown exactly once. | P0 |
| FR-104 | Tokens MUST be stored as hashes with a lookup prefix and MUST be revocable. | P0 |
| FR-105 | Every request MUST resolve to a workspace; every query MUST be scoped by `workspace_id`. | P0 |
| FR-106 | CI mutating endpoints MUST require a token with the matching scope. | P0 |

### 6.2 Services and specs

| ID | Requirement | Priority |
|---|---|---|
| FR-201 | A user MUST be able to create a service with name, owner team, optional repo URL and base URL. | P0 |
| FR-202 | A user MUST be able to upload an OpenAPI 3.0.x/3.1.x file (`.json`, `.yaml`, `.yml`, max 5 MB) to a service. | P0 |
| FR-203 | The system MUST parse and canonicalize the document into the normalized structure defined in `DATA_MODEL.md`. | P0 |
| FR-204 | Every upload MUST create an immutable spec version with a label, content hash, and parse status. | P0 |
| FR-205 | Re-uploading identical content MUST be rejected as a duplicate with `409 ERR-1205`. | P0 |
| FR-206 | Parse failures MUST return actionable validation errors and MUST NOT create a usable version. | P0 |
| FR-207 | A user MUST be able to list, search, and open specs and their version history. | P0 |
| FR-208 | Specs MUST support soft deletion; deleted specs are excluded from graph builds. | P1 |
| FR-209 | The parser MUST reject unsupported OpenAPI versions with `ERR-1202`. | P0 |
| FR-210 | `$ref` resolution MUST be limited to the uploaded document; no remote fetch. | P0 |

### 6.3 Graph

| ID | Requirement | Priority |
|---|---|---|
| FR-301 | The graph service MUST build `Service`, `APIEndpoint`, `SchemaComponent`, and `Consumer` nodes plus `PROVIDES`, `CONSUMES`, `DEPENDS_ON`, `REFERENCES` edges. | P0 |
| FR-302 | Graph builds MUST be deterministic: the same inputs yield the same `graph_version`. | P0 |
| FR-303 | Consumer discovery MUST follow the precedence in ADR-0005: declared `x-consumers`, inferred host/base-URL match, manual binding. | P0 |
| FR-304 | The graph service MUST expose nodes and edges for a spec version with stale-version metadata. | P0 |
| FR-305 | A user MUST be able to trigger a full graph rebuild and see its status. | P0 |
| FR-306 | The UI MUST render an interactive graph with zoom, pan, selection, focus mode, and severity highlighting. | P0 |
| FR-307 | The UI MUST provide an accessible list/table equivalent of the graph. | P0 |
| FR-308 | The graph MUST exclude orphan endpoints and report them as warnings, not crash the build. | P1 |

### 6.4 Diff and detection

| ID | Requirement | Priority |
|---|---|---|
| FR-401 | A user MUST be able to diff any two versions of the same spec. | P0 |
| FR-402 | Diffing MUST run asynchronously and expose `queued`, `running`, `completed`, `failed`. | P0 |
| FR-403 | The engine MUST implement every rule in `BREAKING_CHANGES.md` with the specified severity. | P0 |
| FR-404 | Every change MUST have a stable fingerprint, rule ID, location, before/after values, and human description. | P0 |
| FR-405 | The diff MUST persist summary counts per severity and a `breaking` boolean. | P0 |
| FR-406 | Changes MUST be filterable by severity, breaking flag, method, and path. | P0 |
| FR-407 | The UI MUST present a reviewable change list with severity, rule, location, and a before/after diff. | P0 |
| FR-408 | Unchanged metadata, reordered keys, and description-only edits MUST NOT produce duplicate changes. | P0 |

### 6.5 Impact and migration

| ID | Requirement | Priority |
|---|---|---|
| FR-501 | Impact analysis MUST start from selected changes (or all breaking changes) and resolve affected endpoints. | P0 |
| FR-502 | The system MUST compute direct and transitive consumers using reverse reachability over `DEPENDS_ON`. | P0 |
| FR-503 | The report MUST include each affected service's owner team and the evidence path that made it affected. | P0 |
| FR-504 | The system MUST compute migration order with dependencies before dependents using Kahn's algorithm. | P0 |
| FR-505 | The system MUST group services into migration waves and expose them in the report. | P0 |
| FR-506 | Cycles MUST be detected with Tarjan SCC and reported as a coordinated-release group; the system MUST NOT emit a false linear order. | P0 |
| FR-507 | The report MUST include a summary, affected-service count, critical-change count, graph version, and generation timestamp. | P0 |
| FR-508 | Reports MUST be persisted and listable; a report MUST reference the diff it came from. | P0 |
| FR-509 | A user MUST be able to approve a completed report; approval is recorded with actor and timestamp. | P1 |
| FR-510 | A report MUST provide a print-friendly view suitable for stakeholder review. | P1 |
| FR-511 | Impact analysis MUST fail with a clear stale-graph error if the graph version does not match the diff's spec versions. | P1 |

### 6.6 CI, async reliability, and operations

| ID | Requirement | Priority |
|---|---|---|
| FR-601 | The system MUST expose a CI webhook that accepts a new spec version and optional diff/impact request. | P0 |
| FR-602 | The webhook MUST validate `X-BlastSpec-Signature` HMAC-SHA256 over `timestamp.body` and reject timestamps older than 5 minutes. | P0 |
| FR-603 | Webhook processing MUST be idempotent by `Idempotency-Key`; duplicates return the original result. | P0 |
| FR-604 | State changes MUST write an outbox row in the same Postgres transaction. | P0 |
| FR-605 | A relay worker MUST publish pending outbox rows to Redis Streams with consumer groups and at-least-once delivery. | P0 |
| FR-606 | Failed jobs MUST retry with exponential backoff and move to the DLQ after the configured maximum. | P0 |
| FR-607 | An operator MUST be able to list and replay DLQ jobs without creating duplicate reports. | P1 |
| FR-608 | Every async resource MUST expose status and error detail for polling. | P0 |
| FR-609 | The API MUST return `X-Request-Id` and include `trace_id` in error bodies. | P0 |
| FR-610 | Health endpoints MUST report liveness and readiness for API, graph service, Postgres, Redis, and Kuzu. | P0 |
| FR-611 | The system MUST seed demo data with a single command. | P0 |

## 7. Key user journeys

### J1 — Upload three specs and see the graph

1. User logs into the demo workspace.
2. Creates or selects three services and assigns owner teams.
3. Uploads one spec per service; parser reports endpoints and warnings.
4. Graph builds automatically; user opens the graph explorer.
5. Inferred consumer edges appear dashed; declared edges solid.

### J2 — Detect a breaking change

1. User opens a spec and uploads version `2.0.0`.
2. User selects `1.0.0 → 2.0.0` and starts a diff.
3. The UI shows progress, then a summary: N critical, N warning, N info.
4. User filters to critical changes and reviews before/after values.

### J3 — Generate and approve an impact report

1. From the diff, user selects breaking changes and requests impact analysis.
2. System resolves consumers, owners, waves, and cycles.
3. User reviews affected services and migration order.
4. User approves the report and prints/shares it.

### J4 — CI webhook

1. Pipeline POSTs the new spec with a CI token, HMAC signature, and idempotency key.
2. System records the event, creates the version, runs diff, and optionally impact analysis.
3. Response is `202` with resource links.
4. Retrying the same request returns the same resources, not duplicates.

## 8. Acceptance criteria

| ID | Given / When / Then |
|---|---|
| AC-001 | Given a valid 3.1 spec, when uploaded, then a parsed version exists with endpoint count > 0 and a stable content hash. |
| AC-002 | Given the same content uploaded twice to a spec, when the second upload occurs, then the API returns `409 ERR-1205` and no new version is created. |
| AC-003 | Given a YAML bomb or remote `$ref`, when parsed, then the request fails safely with `422 ERR-1204` and no outbound request is made. |
| AC-004 | Given three specs with `x-consumers`, when the graph builds, then each declared consumer produces a `CONSUMES` edge with source `declared`. |
| AC-005 | Given two services whose base URLs match a spec server, when the graph builds, then an inferred binding exists with confidence < 1 and source `inferred`. |
| AC-006 | Given a removed operation, when diffed, then one `BR-001` change with severity `critical` and `breaking=true` exists. |
| AC-007 | Given a new required request field, when diffed, then one `BR-016` critical change with pointer `/components/schemas/<Name>/required` exists. |
| AC-008 | Given reordered keys and edited descriptions only, when diffed, then zero critical changes exist and metadata changes are `info`. |
| AC-009 | Given a critical change on `GET /users`, when impact runs, then every direct consumer and every transitive dependent appears once with an owner team. |
| AC-010 | Given A depends on B depends on C, when C changes, then migration order is `C, B, A` and waves are `C=0, B=1, A=2`. |
| AC-011 | Given a dependency cycle, when impact runs, then the report marks the SCC as `coordinated_release` and emits no linear order for its members. |
| AC-012 | Given a completed report, when approved, then `approved_at` and `approved_by` are recorded and repeat approval returns `409`. |
| AC-013 | Given a valid signed webhook, when posted, then `202` is returned with diff and report links. |
| AC-014 | Given the same idempotency key and body, when posted again, then the original resource IDs are returned and no duplicate diff or report exists. |
| AC-015 | Given a stale timestamp or bad signature, when posted, then `401` is returned and no spec version is created. |
| AC-016 | Given a job that fails 3 times, when retries exhaust, then a DLQ row exists and the resource status is `failed` with `ERR-1304`. |
| AC-017 | Given a DLQ row, when replayed after the fix, then exactly one new processing attempt occurs and no duplicate change rows exist. |
| AC-018 | Given a graph query while the graph is stale, then the response includes `graph_version` and `stale=true`. |
| AC-019 | Given a 1,000-endpoint graph, when impact analysis runs, then p95 completes in under 10 seconds in the local benchmark. |
| AC-020 | Given keyboard-only navigation, when viewing the graph, then the list fallback exposes all nodes/edges and the report is reachable. |

## 9. Non-functional requirements

| ID | Requirement | Target |
|---|---|---|
| NFR-001 | Spec parse time, 5 MB document | p95 < 5 s |
| NFR-002 | Graph query response, 1k endpoints | p95 < 500 ms |
| NFR-003 | Impact analysis, 5k nodes / 10k edges | p95 < 10 s |
| NFR-004 | UI accessibility | WCAG 2.2 AA; keyboard-complete |
| NFR-005 | Secrets | No secret in logs, responses (except one-time token), or repo |
| NFR-006 | Webhook reliability | Idempotent under retry; at-least-once processing |
| NFR-007 | Browser support | Last two versions of Chrome, Firefox, Safari, Edge |
| NFR-008 | Local bootstrap | `docker compose up --build` + seed < 10 minutes |
| NFR-009 | Availability (demo) | Graceful degradation when graph service is down; clear error state |
| NFR-010 | Contract discipline | `contracts/openapi.yaml` validates in CI; generated types are current |
| NFR-011 | Determinism | Same spec inputs produce identical `graph_version` and change fingerprints |
| NFR-012 | Observability | Every request, job, and graph call carries `trace_id` |

## 10. Success metrics (portfolio demo)

- Demo completes upload → graph → diff → report in under 5 minutes.
- Report contains owners and a migration order, not just a change list.
- A cycle fixture produces a correct coordinated-release warning.
- The same webhook replayed 10 times produces exactly one report.
- A reviewer can explain the product after 10 minutes with `README.md` + this PRD.

## 11. Release criteria

- [ ] All P0 requirements implemented and covered by tests.
- [ ] `contracts/openapi.yaml` validates and generated client types are current.
- [ ] Demo seed produces 3 services, 2 versions, 1 report.
- [ ] E2E flow passes in CI.
- [ ] Accessibility audit passes for the graph fallback and report.
- [ ] `docker compose up` works from a clean checkout.
- [ ] DLQ replay demonstrated.
- [ ] No critical security findings in upload or webhook paths.

## 12. Risks and open questions

| Risk | Impact | Mitigation |
|---|---|---|
| Consumer discovery is inherently incomplete | False negatives in impact | Show provenance/confidence, allow manual bindings, label report coverage |
| OpenAPI structures are deeply nested | Diff complexity | Normalize first; diff canonical structures; golden fixtures |
| Graph and Postgres diverge | Wrong impact | Graph derived and rebuildable; `graph_version` exposed |
| Redis Streams consumer-group bugs | Lost jobs | Ack after success, pending reclaim, DLQ, integration tests |
| Demo-scale performance hides real issues | Weak signal | 1k-endpoint perf fixture in CI |

Open question (resolved for MVP): multi-tenant orgs are deferred; `workspace_id` exists now to avoid a later migration.

## 13. Traceability

Requirements map to implementation tickets in `TASKS.md` and to acceptance tests in `TESTING.md`. Every `FR-###` MUST appear in at least one task. Every P0 `FR-###` MUST have an automated test reference before release.
