---
status: approved
owner: Engineering
last_verified: 2026-11-02
source_of_truth_for:
  - coding standards
  - repo conventions
  - definition of done
related:
  - AGENTS.md
  - ARCHITECTURE.md
  - TESTING.md
  - API_CONTRACT.md
---

# Engineering Rules

These rules are mandatory. If a rule blocks a legitimate change, update this document in the same PR with a short rationale.

---

## 1. Golden rules

1. **Contract first.** Update `contracts/openapi.yaml` before implementing an HTTP change.
2. **Postgres owns truth.** Kuzu and Redis are derived. Never store the only copy of anything outside Postgres.
3. **One writer per store.** Only the graph service opens Kuzu. Only Laravel writes Postgres domain tables.
4. **Transactions include outbox.** A domain write and its event commit together or not at all.
5. **Handlers are idempotent.** Every async handler can run twice without duplicating state.
6. **No business logic in controllers, routers, or React components.**
7. **Tests first for algorithms and rules.** A new `BR-###` without a fixture is incomplete.
8. **No secrets, no `any`, no `@ts-ignore`, no silenced errors.**
9. **Docs are part of the change.** Schema, contract, and behavior changes update the owning doc in the same PR.
10. **Prefer deletion over abstraction.** Do not add a layer until there are two real callers.

---

## 2. Repository layout

```text
blastspec/
├── frontend/
│   └── src/
│       ├── app/                    # routes, layouts, loading, error
│       ├── components/
│       │   ├── ui/                 # design-system primitives
│       │   └── features/           # graph, diff, reports, specs
│       ├── lib/
│       │   ├── api/                # client + generated schema
│       │   ├── schemas/            # zod form schemas
│       │   └── format/             # dates, numbers, severity
│       └── styles/                 # globals.css, print.css
├── backend/
│   ├── app/
│   │   ├── Actions/                # one use case per class
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Jobs/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/
│   │   │   ├── Diff/
│   │   │   ├── Graph/
│   │   │   ├── Outbox/
│   │   │   └── Spec/
│   │   └── Support/
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/api.php
│   └── tests/
│       ├── Feature/
│       ├── Unit/
│       └── Fixtures/
├── graph-service/
│   ├── src/graph_service/
│   │   ├── main.py
│   │   ├── api/
│   │   ├── core/
│   │   ├── graph/
│   │   │   ├── algorithms.py
│   │   │   ├── repository.py
│   │   │   └── schema.py
│   │   └── models.py
│   └── tests/
├── contracts/openapi.yaml
├── infra/
│   ├── docker-compose.yml
│   ├── perf/
│   └── k8s/                        # optional, not MVP
├── docs/
└── .github/workflows/
```

Do not create new top-level folders without an ADR.

---

## 3. Naming

| Context | Convention | Example |
|---|---|---|
| PHP class | PascalCase | `CreateImpactReport` |
| PHP method | camelCase | `execute()` |
| Python module/function | snake_case | `reverse_reachability` |
| Python class | PascalCase | `GraphRepository` |
| TS component | PascalCase export, kebab-case file | `severity-chip.tsx` → `SeverityChip` |
| TS function/variable | camelCase | `formatSeverity` |
| JSON fields | snake_case | `affected_services` |
| DB table | snake_case plural | `impact_report_items` |
| DB column | snake_case | `owner_team` |
| Enum case (PHP) | PascalCase | `Severity::Critical` |
| Enum value (DB/JSON) | lowercase | `critical` |
| Graph label | PascalCase | `APIEndpoint` |
| Graph relationship | SCREAMING_SNAKE | `DEPENDS_ON` |
| Env var | SCREAMING_SNAKE | `GRAPH_SERVICE_TOKEN` |
| Redis stream | lowercase colon-delimited | `blastspec:impact` |
| Branch | `type/kebab-summary` | `feat/graph-explorer` |
| Commit | Conventional Commits | `feat(graph): add focus mode` |

---

## 4. Laravel conventions

### 4.1 Structure

- `declare(strict_types=1);` in every PHP file.
- Classes are `final` unless designed for extension.
- One public method per Action: `execute(...)`.
- Controllers: authorize → validate via FormRequest → call Action → return Resource. No queries, no branching beyond presentation.
- Models: relationships, casts, accessors, and query scopes only. No business logic, no HTTP, no jobs.
- Use API Resources for every response. Never return an Eloquent model directly.
- Use backed enums for statuses and severities.
- DTOs are readonly classes built from validated input.
- Dependency injection via constructor; no service locator, no `app()` in domain code.

### 4.2 Methods

- Maximum ~40 lines per method; extract when it exceeds.
- Maximum 4 parameters; use a DTO beyond that.
- Explicit parameter and return types on every method.
- No `mixed` unless interacting with a third-party untyped API, then narrow immediately.
- No boolean flag parameters that change behavior; use two methods or an enum.

### 4.3 Database

- Wrap multi-write use cases in `DB::transaction`.
- Write the outbox row inside the same transaction.
- No `env()` outside `config/`.
- No raw SQL with interpolated input. Parameter binding only.
- Eager-load relationships used by a Resource to avoid N+1.
- Add an index for every foreign key and every column used in a list filter.
- Use `timestamptz`, UUIDs, and explicit `ON DELETE` behavior.
- Migrations are forward-only; never edit a merged migration.

### 4.4 Validation

- FormRequest for HTTP input; never validate in controllers.
- Validation messages are user-facing and match `DESIGN.md` voice.
- Upload validation mirrors `SECURITY.md` limits exactly; one source of truth in a config class.
- Normalize input in the FormRequest (`prepareForValidation`) when it is purely syntactic.

### 4.5 Errors

- Throw domain exceptions; map them to `Problem` responses in one handler.
- Never return `200` with an error payload.
- Never expose exception messages for 500s; log the detail and return `ERR-9001` with a trace ID.
- Use the documented `ERR-###` codes only. Add new codes to `API_CONTRACT.md` first.

### 4.6 Jobs and streams

- Job handlers implement `handle(array $payload): void` and are idempotent.
- Check current state before writing; use unique constraints as the final guard.
- Acknowledge the stream message only after the transaction commits.
- Log job start/end with `job_id`, `job_type`, `attempt`, `aggregate_id`.
- No `sleep()` inside handlers; schedule retries via delayed re-publish.

### 4.7 PHP style

- Pint with the project preset; CI runs `--test`.
- Larastan level 6 minimum; no baseline additions without review.
- Imports sorted; no unused imports.
- Prefer early returns.
- No commented-out code.

---

## 5. Graph service conventions

### 5.1 Structure

- Routers are thin: parse → call service/repository → return model.
- Pure algorithms live in `graph/algorithms.py` and take in-memory data; they perform no I/O.
- Kuzu access lives only in `graph/repository.py`.
- Pydantic v2 models for every request and response.
- Settings via `pydantic-settings`; no `os.environ` scattered in code.
- Lifespan opens and closes the Kuzu database exactly once.

### 5.2 Types and style

- `from __future__ import annotations` in every module.
- Full type hints; `mypy --strict` passes.
- Ruff for lint and format; line length 100.
- No mutable module-level state.
- Use `dataclasses` or Pydantic models for internal structures; no untyped dicts crossing module boundaries.
- `async def` for I/O; plain `def` for CPU-bound endpoints so FastAPI runs them in a threadpool. Never block the event loop with graph traversal.
- Timeouts on every Kuzu query and on the whole request.

### 5.3 Errors and safety

- Map domain errors to the same `Problem` envelope with `ERR-14xx`.
- Never interpolate values into Cypher; parameters only.
- Validate response sizes; cap nodes/edges before serialization.
- Log `graph_version`, counts, and duration, never full graph payloads.
- The repository is the only place that calls `kuzu.Connection.execute`.

### 5.4 Determinism

- Sort every collection before hashing or ordering.
- UUIDv5 for derived IDs.
- No timestamps in `graph_version`.
- Tests assert byte-identical output for the same input twice.

---

## 6. Frontend conventions

### 6.1 Rendering

- Next.js App Router. Server Components by default.
- `"use client"` only on the smallest interactive leaf.
- Data fetching:
  - Server Components fetch initial data.
  - TanStack Query only for polling and client mutations.
  - Never fetch in `useEffect`.
- Route state (filters, selected version) lives in `searchParams`, not global state.
- No global state library in MVP. Use React context only for the session and sidebar.

### 6.2 Types and validation

- TypeScript `strict: true`.
- API types come from `frontend/src/lib/api/schema.d.ts` (generated). Never hand-write response types.
- No `any`, no `as` casts to silence errors, no `@ts-ignore`.
- Use `unknown` at boundaries and narrow with zod.
- Zod schemas only for forms and local parsing; the API contract remains authoritative.
- All dates formatted through `lib/format`.

### 6.3 Components

- Components ≤ 150 lines. Extract subcomponents or hooks.
- No business logic in JSX; derive with named functions.
- Props are explicit and readonly.
- `components/ui` contains primitives only; `components/features` contains product behavior.
- Every list has a stable `key` from the domain ID.
- Every async view has loading, empty, error, and success states.

### 6.4 Styling

- Tailwind with design tokens from `globals.css`.
- No raw hex, no arbitrary colors, no gradient utilities.
- No inline `style` except computed graph coordinates.
- Use `cn()` for conditional classes.
- Respect `prefers-reduced-motion`.

### 6.5 Accessibility

- Semantic HTML first.
- Every input has a label.
- Icon-only buttons have `aria-label`.
- Dialogs trap focus and restore it.
- Announce async status changes with `aria-live`.
- Never remove focus outlines.
- Test keyboard paths for new screens.

### 6.6 Performance

- No client component above the fold unless it needs interactivity.
- Lazy-load the graph canvas and the diff viewer.
- Avoid barrel files that defeat tree-shaking.
- Images use `next/image`; no layout shift.
- Keep the initial route JS lean; performance budgets live in `TESTING.md` §12 and are validated by the performance smoke.

---

## 7. API rules

- `contracts/openapi.yaml` changes first.
- Response fields are `snake_case`; TypeScript maps them as generated.
- Async mutations return `202` with a `Location` header and a pollable resource.
- Lists use cursor pagination, never offset.
- Every mutating endpoint has an authorization check and a workspace scope.
- Every error uses the `Problem` envelope and a documented `ERR-###`.
- Never leak stack traces, SQL, file paths, or internal hostnames.
- Idempotency keys are required on webhooks and honored on other POSTs when supplied.
- Do not add an endpoint that duplicates an existing one for UI convenience.

---

## 8. Async and reliability rules

- Outbox write is in the domain transaction.
- Relay uses `FOR UPDATE SKIP LOCKED`.
- Consumers use a consumer group and acknowledge after commit.
- Pending entries are reclaimed after 60 seconds.
- Max 3 attempts, exponential backoff with jitter.
- After exhaustion: write `dlq_jobs`, mark the resource `failed`, log with the trace ID.
- Replay is idempotent and preserves the original payload.
- No unbounded retries. No silent failures.

---

## 9. Security rules

- Never commit `.env`, tokens, keys, or fixtures containing real secrets.
- Logs redact `Authorization`, `Cookie`, `X-BlastSpec-Signature`, `X-Internal-Token`, `password`, `plaintext`.
- Uploads enforce size, type, depth, alias, and `$ref` limits from `SECURITY.md`.
- `$ref` resolution is local only.
- Hash CI tokens; compare with `hash_equals`.
- Verify webhook HMAC over the raw body before JSON decoding.
- Render user content as text; sanitize markdown.
- Parameterize Cypher and SQL.
- Foreign workspace IDs return `404`.

---

## 10. Logging and observability

- Structured JSON in production; human-readable locally.
- Include `trace_id` in every request, job, and graph call.
- Log outcomes, not payloads.
- Never log raw spec content; log `content_hash` and `bytes`.
- Use levels consistently: `error` for failed work, `warning` for degraded, `info` for state transitions, `debug` for development only.
- No `console.log`, `dd()`, `dump()`, `var_dump()`, or `print()` in committed code.

---

## 11. Testing rules

- New behavior: failing test first, then implementation.
- New `BR-###`: before/after/expected fixtures.
- New endpoint: contract test + foreign-workspace test.
- New job: idempotency test + retry/DLQ test.
- New algorithm: unit test + invariant/property test where applicable.
- No network in unit tests.
- Freeze time and IDs.
- No `sleep()` for synchronization.
- Coverage gates from `TESTING.md` apply.

---

## 12. Git and pull requests

### Branches

```text
feat/<scope>-<summary>
fix/<scope>-<summary>
docs/<summary>
test/<summary>
refactor/<scope>-<summary>
chore/<summary>
```

### Commits

Conventional Commits, imperative, ≤ 72 characters:

```text
feat(graph): add focus mode to explorer
fix(diff): dedupe enum changes by fingerprint
docs(data): document consumer binding provenance
test(impact): cover cycle wave assignment
```

### Pull requests

- One logical change; target < 400 changed lines excluding fixtures and generated files.
- Description: what, why, how tested, docs updated, risk.
- Link the `TASK-###` and `FR-###`.
- Include screenshots for UI changes and a short clip for graph interaction.
- Never mix refactor and behavior change.
- Never commit generated files by hand; regenerate.
- All CI gates green before merge.

---

## 13. Definition of Done

A task is done only when:

- [ ] Behavior matches the linked `FR-###` and acceptance criteria.
- [ ] Tests exist and pass; coverage gates hold.
- [ ] `contracts/openapi.yaml` updated and regenerated types committed.
- [ ] `DATA_MODEL.md` updated if the schema changed.
- [ ] `BREAKING_CHANGES.md` updated if a rule changed.
- [ ] `GRAPH_ALGORITHMS.md` updated if algorithm semantics changed.
- [ ] `DESIGN.md` / `SCREENS.md` updated if UI changed.
- [ ] Errors use documented codes and user-facing copy.
- [ ] Logs include `trace_id` and no sensitive data.
- [ ] No new lint, type, or security warnings.
- [ ] Accessibility checked for new UI.
- [ ] No secrets, debug statements, or commented-out code.
- [ ] PR description explains risk and rollback.

---

## 14. Forbidden patterns

| Forbidden | Why | Use instead |
|---|---|---|
| `any`, `@ts-ignore`, `as unknown as` | Hides contract drift | Generated types, zod narrowing |
| `env()` in application code | Breaks config caching | `config()` |
| `DB::raw` with input | SQL injection | Bindings / query builder |
| Cypher f-strings | Injection | Parameters |
| Business logic in controllers/routers/models/JSX | Untestable | Actions/services/pure functions |
| `SELECT *` in raw SQL | Fragile | Explicit columns |
| `sleep()` in tests | Flaky | Wait on state |
| `console.log`, `dd()`, `dump()` | Noise, data leaks | Structured logger |
| Editing generated files | Drift | Regenerate |
| `dangerouslySetInnerHTML` | XSS | Text rendering or sanitizer |
| Wildcard CORS with credentials | Security | Explicit origins |
| `latest` container tags | Non-reproducible | Pinned digests |
| Unbounded retries | Outages | Max 3 + DLQ |
| Silent `catch {}` | Hidden failures | Log + map to error |
| Commented-out code | Rot | Delete it |

---

## 15. Tooling versions

Pin versions at scaffold time and record them in `README.md`. Do not upgrade majors mid-phase without an ADR. Minimums:

- PHP 8.3, Laravel 12
- Python 3.12, FastAPI, Pydantic v2, Kuzu
- Node 22 LTS, Next.js App Router, TypeScript 5.x
- PostgreSQL 16, Redis 7
