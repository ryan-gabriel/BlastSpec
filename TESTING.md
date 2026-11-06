---
status: approved
owner: QA
last_verified: 2026-11-02
source_of_truth_for:
  - test strategy
  - fixtures
  - CI gates
related:
  - PRD.md
  - BREAKING_CHANGES.md
  - GRAPH_ALGORITHMS.md
  - RULES.md
---

# Testing

## 1. Principles

1. **Test behavior, not implementation.** Assert API responses, persisted rows, graph output, and rendered UI.
2. **Every P0 requirement has an automated test.** No exceptions at release.
3. **Every `BR-###` rule has a fixture pair.** The taxonomy is executable.
4. **Determinism is a feature.** Freeze time, seed randomness, sort collections, and never call the network in unit tests.
5. **No coverage theater.** A covered line with no meaningful assertion does not count.
6. **A flaky test is a bug.** Quarantine it, fix it, or delete it; never add retries to hide it.

---

## 2. Test layers

| Layer | Tool | Scope | Speed |
|---|---|---|---|
| Unit (backend) | Pest | Actions, DTOs, canonicalizer, rule engine | ms |
| Unit (graph) | pytest | Reachability, Tarjan, Kahn, waves, fingerprints | ms |
| Unit (frontend) | Vitest + Testing Library | Formatters, hooks, presentational components | ms |
| Integration | Pest + real Postgres/Redis | Transactions, outbox, streams, DLQ, auth | s |
| Contract | OpenAPI validation + generated types | Request/response shape | s |
| E2E | Playwright | Upload → graph → diff → report, webhook, auth | min |
| Performance | pytest benchmark + k6 | NFR budgets | min |
| Docs | markdownlint + link check | Links, IDs, Mermaid | s |

---

## 3. Commands

### Backend (Laravel)

```bash
composer install
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pest --coverage --min=80
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
```

### Graph service (FastAPI)

```bash
uv sync --all-extras
uv run pytest
uv run pytest --cov=src --cov-branch --cov-fail-under=90
uv run ruff check .
uv run ruff format --check .
uv run mypy src
```

### Frontend (Next.js)

```bash
npm ci
npm run lint
npm run typecheck
npm run test
npx playwright install --with-deps
npx playwright test
```

### Contract and docs

```bash
npx @redocly/cli lint contracts/openapi.yaml
npm run api:generate
git diff --exit-code frontend/src/lib/api/schema.d.ts
npx markdownlint-cli2 "**/*.md"
npx lychee --offline --include-fragments .
```

---

## 4. Coverage gates

| Area | Gate |
|---|---|
| Laravel | 80% lines overall; 90% on `app/Services/Diff` |
| Graph service | 90% branch on `src/graph`; 80% overall |
| Frontend | No numeric gate; all critical flows covered by Vitest or Playwright |
| Contract | 100% of documented endpoints exercised at least once |

Coverage is reported but never allowed to fail a build for a single uncovered line in generated code, migrations, or configuration.

---

## 5. Fixtures

### 5.1 Spec fixtures

```text
backend/tests/Fixtures/specs/
├── petstore/
│   ├── v1.yaml
│   ├── v2-breaking.yaml
│   └── v2-compatible.yaml
├── breaking/
│   └── BR-001/
│       ├── before.yaml
│       ├── after.yaml
│       └── expected.json
├── multi-service/
│   ├── billing.yaml
│   ├── checkout.yaml
│   └── notifications.yaml
├── malformed/
│   ├── invalid-yaml.yaml
│   ├── remote-ref.yaml
│   ├── deep-nesting.yaml
│   ├── alias-bomb.yaml
│   └── oversized.yaml
└── graph/
    ├── linear.json
    ├── diamond.json
    ├── cycle.json
    ├── self-loop.json
    ├── disconnected.json
    └── 1000-endpoints.json
```

Rules:

- One directory per `BR-###`. `before.yaml` and `after.yaml` are minimal documents containing only the structures needed for the rule.
- `expected.json` contains the exact change rows: `rule_id`, `severity`, `breaking`, `method`, `path`, `pointer`, `before`, `after`, `fingerprint`.
- Golden files are reviewed like code. A change to `expected.json` requires a documented reason.
- `multi-service/` uses `x-consumers` and matching `servers[].url` values to exercise all three discovery sources.

### 5.2 Graph fixtures

Graph fixtures are JSON snapshots of services, endpoints, bindings, and expected results. They do not require Kuzu; algorithms accept an in-memory adjacency structure.

### 5.3 Webhook vectors

```text
backend/tests/Fixtures/webhooks/
├── valid.json
├── wrong-signature.json
├── expired-timestamp.json
└── reused-key-different-body.json
```

Each vector includes the raw body, timestamp, secret, and expected signature.

---

## 6. Backend test matrix

| Area | Cases |
|---|---|
| Auth | Login success/failure, session expiry, CSRF rejection, logout |
| Tokens | Create, plaintext once, scope enforcement, revocation, foreign workspace |
| Services | Create, duplicate slug, owner required, update, soft delete |
| Upload | Valid JSON/YAML, 3.0/3.1, duplicate hash, duplicate label, oversized, wrong type, parse failure creates nothing |
| Parser safety | Remote `$ref`, alias bomb, deep nesting, node cap, timeout |
| Canonicalization | Path normalization, parameter sorting, enum sorting, nullable normalization, key sorting |
| Spec versions | Immutability, current version update, version list order |
| Diff | Each `BR-###` fixture, reordering no-op, metadata-only info, fingerprint stability, duplicate merge |
| Impact | Provider only, direct, transitive, diamond dedupe, missing owner, stale graph, cycle |
| Reports | Create, poll, complete, approve, double approve, list filter, persistence |
| Webhook | Valid, bad signature, stale timestamp, duplicate same body, duplicate different body, unknown event |
| Outbox | Written in transaction, relay marks processed, retry, duplicate delivery safe |
| DLQ | Three failures, row created, replay, no duplicate changes |
| Rate limits | Login, upload, webhook |
| Errors | Envelope shape, `trace_id`, no internal detail |

---

## 7. Graph service test matrix

| Area | Cases |
|---|---|
| Reverse reachability | No consumers, direct, transitive, diamond, depth cap, unknown endpoint |
| Ordering | Linear, diamond, disconnected, deterministic tie-break, same input twice |
| SCC | 2-cycle, 3-cycle, self-loop, nested SCCs, acyclic graph |
| Waves | Contiguity, parallelism, SCC shared wave, dependency monotonicity |
| Sync | Idempotent, snapshot hash mismatch, rollback on failure, delete-and-reinsert |
| Queries | Parameterization, workspace isolation, version filter |
| Limits | Node/edge caps, timeout response |
| Health | Kuzu open, read-only path, missing directory |

Property-based tests (Hypothesis) generate random graphs and assert the invariants in `GRAPH_ALGORITHMS.md` §11.

---

## 8. Frontend test matrix

| Area | Cases |
|---|---|
| Upload form | Valid file, wrong type, oversized, server validation, progress |
| Graph explorer | Render nodes/edges, select, focus, zoom controls, legend, list fallback, empty state |
| Diff review | Severity filters, before/after display, pagination, no changes |
| Report | Loading/queued/failed/completed, waves, cycles, approval, print layout |
| Tokens | Create, reveal once, copy, revoke, confirm dialog |
| Auth | Login, logout, expired session redirect |
| Accessibility | Keyboard-only graph fallback, focus order, labels, axe scan |
| Errors | Problem envelope rendering, retry actions |

Use MSW for API mocking. Do not snapshot large DOM trees; assert roles, labels, and text.

---

## 9. Contract tests

1. `contracts/openapi.yaml` passes Redocly lint with no errors.
2. Every Laravel feature test validates its response against the schema (`league/openapi-psr7-validator` or equivalent).
3. `npm run api:generate` produces no diff in `schema.d.ts`.
4. Error responses validate against `Problem`.
5. Webhook signature vectors match the documented canonical string.
6. Internal graph service responses are validated with Pydantic models and mirrored in `API_CONTRACT.md`.

---

## 10. End-to-end flows

### E2E-1 — Happy path

1. Log in as the demo user.
2. Open the seeded billing spec.
3. Upload `v2-breaking.yaml`.
4. Wait for the graph to sync.
5. Create a diff against `v1`.
6. Assert at least one critical change.
7. Generate an impact report.
8. Assert checkout and notifications appear with owners.
9. Assert waves are present and ordered.
10. Approve the report and open the print view.

### E2E-2 — CI webhook

1. Create a token with `webhooks:write`.
2. Sign a `spec.published` payload.
3. POST the webhook.
4. Assert `202` with diff/report IDs.
5. Replay the same request and assert `duplicate=true` and identical IDs.
6. Assert exactly one diff and one report exist.

### E2E-3 — Failure and recovery

1. Stop the graph service.
2. Trigger impact analysis.
3. Assert `503 ERR-1401` and a clear UI state.
4. Start the graph service.
5. Rebuild and retry; assert success.

### E2E-4 — Cycle

1. Load the cycle fixture.
2. Generate a report.
3. Assert the cycle group is shown, no linear order is claimed, and the warning copy is visible.

---

## 11. Reliability tests

| Test | Assertion |
|---|---|
| Outbox transaction rollback | No outbox row when the domain write fails |
| Relay crash after publish | Row remains `processing` and is retried; handler is idempotent |
| Worker crash mid-job | Pending entry is reclaimed after 60 s; no duplicate rows |
| Duplicate outbox delivery | Handler produces one resource |
| Webhook 10× replay | One version, one diff, one report |
| DLQ after 3 failures | Exactly one DLQ row; resource `failed` |
| DLQ replay | One new attempt; no duplicate changes |
| Graph sync failure | Previous graph remains queryable; build marked failed |
| Stale graph | `ERR-1402` and `stale=true` |

---

## 12. Performance tests

| Test | Setup | Budget |
|---|---|---|
| Spec parse | 5 MB, 1k endpoints | p95 < 5 s |
| Graph query | 1k endpoints, 5k edges | p95 < 500 ms |
| Impact analysis | 5k nodes, 10k edges | p95 < 10 s |
| Report render | 200 affected services | first content < 2 s |
| Graph render | 500 nodes | layout < 1 s |

- Backend: `k6` script in `infra/perf/`.
- Graph: `pytest-benchmark` with a fixed seed.
- Frontend: Playwright trace + Lighthouse CI for the report page.
- Performance failures are release blockers only for P0 budgets.

---

## 13. Determinism rules

- Freeze the clock (`Carbon::setTestNow`, `freezegun`).
- Use UUIDv7/ULID factories with a fixed seed in tests.
- Never depend on filesystem ordering; sort.
- No outbound network. Mock the graph service in backend tests; mock HTTP in frontend tests.
- Reset Redis and Kuzu between tests; use a unique workspace per test where isolation matters.
- Use a dedicated Postgres database per CI shard; transactions roll back per test.

---

## 14. CI pipeline

```text
lint-docs ─┐
lint-api   ─┤
lint-graph ─┼─> test-backend ─┐
lint-web   ─┤                 ├─> e2e ─> release-gate
contract   ─┘   test-graph  ──┤
                test-web    ──┘
```

Gates:

- Any lint failure blocks merge.
- Backend and graph coverage below threshold blocks merge.
- Contract drift blocks merge.
- E2E fails on a required flow blocks merge.
- Security scans (`composer audit`, `npm audit`, `pip-audit`, gitleaks) block merge on high/critical.

---

## 15. Flaky-test policy

1. A test that fails intermittently without a code change is quarantined within one business day.
2. Quarantined tests are tagged `@quarantined`, skipped in required CI, and tracked in `TASKS.md`.
3. No `sleep()` for synchronization; wait on observable state.
4. E2E may retry once in CI only, never locally.
5. A quarantined P0 flow blocks release until fixed.

---

## 16. PR test checklist

- [ ] New behavior has a failing test first (or a documented reason not to).
- [ ] Every new `BR-###` rule has before/after/expected fixtures.
- [ ] Every new endpoint has a contract test and a foreign-workspace test.
- [ ] Every new job has an idempotency test.
- [ ] Every migration has a rollback test or a documented reason none is needed.
- [ ] No test depends on wall-clock time, network, or execution order.
- [ ] Coverage gates pass locally.
- [ ] Flaky tests are not introduced.
