---
status: approved
owner: Design
last_verified: 2026-11-02
source_of_truth_for:
  - route map
  - screen states
  - UI copy
related:
  - DESIGN.md
  - PRD.md
  - API_CONTRACT.md
---

# Screens

Every screen must implement **loading, empty, error, and success** states. Lists also implement **partial** (some data failed). Copy below is normative; use it exactly unless a change is reviewed with design.

---

## 1. Route map

| Route | Screen | Auth |
|---|---|---|
| `/login` | Demo login | Public |
| `/` | Overview | Session |
| `/specs` | Specs list | Session |
| `/specs/upload` | Upload spec | Session |
| `/specs/[specId]` | Spec overview | Session |
| `/specs/[specId]/graph` | Graph explorer | Session |
| `/specs/[specId]/versions` | Version history | Session |
| `/specs/[specId]/diff` | Diff review (`?from=&to=`) | Session |
| `/reports` | Impact reports list | Session |
| `/reports/[reportId]` | Impact report | Session |
| `/reports/[reportId]/print` | Print view | Session |
| `/bindings` | Consumer bindings | Session |
| `/settings/tokens` | CI tokens | Session |
| `/settings/webhooks` | Webhook events | Session |
| `/404` | Not found | Any |

Global shell:

- Sidebar: Overview, Specs, Reports, Bindings, Settings.
- Top bar: breadcrumb, workspace name, user menu.
- Sidebar collapses to icons at < 1280 px and becomes a drawer at < 1024 px.

---

## 2. `/login`

**Purpose:** enter the demo workspace.

**Layout:** split screen. Left 55%: graphite panel with the wordmark, a one-line product statement, and a miniature graph motif drawn in hairline strokes. Right 45%: login form, max width 360.

**Content**

- Wordmark: `BlastSpec`
- Statement: `See what breaks before you ship.`
- Email field, password field, **Sign in**
- Helper: `Demo credentials are pre-filled.`

**States**

- Loading: button label `Signing in…`, disabled.
- Error: `Email or password is incorrect.` above the form.
- Rate limited: `Too many attempts. Try again in 60 seconds.`

**Behavior:** redirects to `/` on success; preserves `?next=` only for same-origin paths.

**Accessibility:** single `h1` (`Sign in to BlastSpec`), labels, autocomplete attributes, error announced.

---

## 3. `/` — Overview

**Purpose:** answer "what needs attention?" in one screen.

**Data:** spec count, service count, reports count, critical changes in the last 30 days, recent reports, recent specs, graph health.

**Layout**

1. Page header: `Overview`, subtitle `Demo workspace · 3 services`.
2. Four stat blocks in a single row (no cards inside cards): Specs, Services, Open impact reports, Critical changes (30d).
3. Two-column section:
   - Left: **Recent reports** table (report, diff, affected, status, created).
   - Right: **Workspace health** panel: graph version, last sync, worker status, DLQ count.
4. Bottom: **Recent specs** table.

**States**

- Empty: `No specs yet. Upload an OpenAPI document to build your first graph.` → **Upload spec**
- Graph degraded: warning banner `Graph service is unavailable. Uploads still work; impact analysis is paused.` → **Check health**
- DLQ > 0: warning row `3 jobs need attention.` → **Open DLQ** (links to `/settings/webhooks#dlq`)

**Non-goals:** charts without a decision attached, vanity metrics, activity feeds.

---

## 4. `/specs`

**Purpose:** find and open specs.

**Data:** specs with service, owner team, current version, endpoint count, last updated.

**Layout**

- Header: `Specs`, primary **Upload spec**.
- Toolbar: search (name, service, path), service filter, owner filter.
- Table columns: Spec, Service, Owner, Version, Endpoints, Updated.
- Row click opens `/specs/[id]`; service name links to filtered list.

**States**

- Loading: 8 static skeleton rows at 44 px.
- Empty (no specs): message + **Upload spec**.
- Empty (filtered): `No specs match these filters.` → **Clear filters**.
- Error: `Specs could not be loaded.` → **Retry**.

---

## 5. `/specs/upload`

**Purpose:** upload the first version of a spec.

**Layout:** single column, max 720. Stepper: 1 Service → 2 Document → 3 Review.

**Step 1 — Service**

- Select an existing service or **Create service** inline.
- Create fields: name, owner team, repo URL (optional), base URL (optional).
- Copy: `Owner team is required. Impact reports use it to tell you who to notify.`

**Step 2 — Document**

- Drop zone: `Drop an OpenAPI file or browse. JSON or YAML, up to 5 MB.`
- Version label field, default `1.0.0`.
- Inline validation as soon as a file is selected: size, type, parse result.

**Step 3 — Review**

- Summary: service, owner, version, OpenAPI version, endpoint count, warnings.
- Warnings list (for example `2 operations have no operationId`).
- Primary **Upload and build graph**.

**States**

- Uploading: progress bar with bytes, not a spinner.
- Parse failed: error panel with pointer and message; **Choose another file**.
- Duplicate: `This exact document already exists as version 1.0.0.` → **View version**.
- Success: redirect to `/specs/[id]/graph` with a toast `Spec uploaded. Graph is building.`

**Non-goals:** editing the document in the browser, multi-file upload.

---

## 6. `/specs/[specId]` — Spec overview

**Purpose:** the home for one spec.

**Layout**

- Header: spec name, service chip, owner team, current version, actions: **Upload version**, **Run diff**, **Open graph**.
- Summary strip: endpoints, consumers, critical changes in latest diff, last graph sync.
- Tabs: Overview, Graph, Versions, Diffs.
- **Overview** tab:
  - Description and metadata.
  - Endpoint table: method, path, summary, consumers, last change.
  - Consumer panel: declared/inferred counts, **Manage bindings**.
- Context rail: service details, repo link, base URL, `x-consumers` status.

**States**

- Graph pending: banner `Graph is building for version 2.0.0.` with progress.
- Graph failed: `Graph build failed. ERR-1403.` → **Rebuild graph**.
- No consumers: `No consumers found. Add x-consumers to the spec or declare a binding.` → **Add binding**.

---

## 7. `/specs/[specId]/graph` — Graph explorer

**Purpose:** see dependencies and blast radius.

**Layout**

- Full-height canvas below a 56 px toolbar.
- Toolbar: version selector, search, severity filter, **Show endpoints**, **Show schemas**, **List view**, **Fit**.
- Left bottom: legend (solid = declared, dashed = inferred, severity colors).
- Right drawer (320) when a node is selected: name, type, owner, endpoints/consumers, severity, actions (`Focus`, `Open spec`, `Copy ID`).

**States**

- Loading: canvas with static grid and a centered status line `Loading graph…`.
- Empty: `This spec has no endpoints yet.` → **View parse warnings**.
- Stale: banner `Graph was built before version 2.0.0.` → **Rebuild**.
- Graph service down: `Graph is unavailable. Uploads and diffs still work.` → **Retry**.
- > 500 nodes: banner `Large graph. Showing services only.` → **Show all**.

**Accessibility**

- First focusable element: **List view**.
- List view table columns: Service, Owner, Relation, Depth, Critical changes, Endpoints.
- Keyboard: Tab through nodes, Enter select, Escape clear, `+`/`-` zoom, `0` fit.

**Non-goals:** free-form node dragging persisted to the server, graph editing, time-travel animation.

---

## 8. `/specs/[specId]/versions` — Version history

**Purpose:** inspect and compare versions.

**Layout:** table: Version, OpenAPI, Endpoints, Warnings, Content hash, Created, Actions (`Diff from previous`, `View`).

- Primary **Upload version**.
- Selecting two versions enables **Compare selected**.
- Diff action navigates to `/specs/[id]/diff?from=<a>&to=<b>`.

**States**

- Single version: `Only one version. Upload a new version to compare.`
- Parse-failed version: red chip `Parse failed`, actions limited to `View error`.
- Duplicate upload: inline error with the existing version link.

---

## 9. `/specs/[specId]/diff` — Diff review

**Purpose:** review changes and start impact analysis.

**Layout**

- Header: `Diff 1.0.0 → 2.0.0`, status chip, **Generate impact report**.
- Summary strip: Critical, Warning, Info, Total.
- Toolbar: severity filter, `Breaking only` toggle, method filter, path search.
- Change list (left 2/3): each row shows severity chip, rule ID, method + path, description, pointer.
- Detail panel (right 1/3): selected change with before/after diff, rule explanation, fingerprint.
- Footer: selection count and primary action.

**States**

- Queued/running: status panel with steps (`Parsing versions`, `Comparing endpoints`, `Detecting changes`, `Saving results`), poll every 2 s.
- Failed: `Diff failed. ERR-1304.` → **Retry diff**.
- No changes: `No contract changes between these versions.` → **Choose other versions**.
- No breaking changes: `No breaking changes. 4 informational changes.` Primary action still available and labeled **Generate informational report**.

**Copy**

- `Generate impact report` is disabled until the diff is completed.
- If no breaking changes, button label becomes `Generate informational report`.

---

## 10. `/reports`

**Purpose:** find reports.

**Layout:** table: Report, Diff, Affected services, Owners, Waves, Status, Created. Filters: status, spec, date.

**States**

- Empty: `No impact reports yet. Run a diff and select breaking changes.` → **View specs**
- Failed: row action **View error**.
- Approved: success chip and approval timestamp.

---

## 11. `/reports/[reportId]` — Impact report

**Purpose:** the deliverable. Must be readable by a manager and actionable by an engineer.

**Layout**

1. Header: `Impact report`, diff link (`billing 1.0.0 → 2.0.0`), status, **Print**, **Approve report**.
2. Summary band: affected services, critical changes, waves, cycles.
3. **What changed** — the critical changes that drove the report, each with rule, method/path, and description.
4. **Who is affected** — table: service, owner team, relation, depth, reason. Group by wave toggle.
5. **Migration plan** — waves as ordered blocks:
   ```text
   Wave 0  billing · Payments
   Wave 1  checkout · Storefront · notifications · Growth
   Wave 2  analytics · Data
   ```
   Each wave is a panel with service chips and owner teams.
6. **Cycles** — warning panel when present: `These services depend on each other and cannot be migrated in a linear order. Coordinate a single release or break the cycle.` Lists members and recommended break.
7. **Coverage** — inferred bindings count and a note: `2 bindings were inferred. Confirm with the owning teams.`
8. Footer: generated timestamp, graph version, engine version.

**States**

- Queued/running: same step pattern as diff (`Resolving endpoints`, `Finding consumers`, `Ordering migration`, `Saving report`).
- Failed: `Impact analysis failed. ERR-1603.` → **Retry** / **View DLQ**.
- Stale graph: `Graph is stale for this diff.` → **Rebuild graph**.
- Empty affected set: not possible; show provider row.
- Approved: banner `Approved by Demo User on 2 Nov 2026, 14:03 UTC.` Approve button disabled.

**Approval dialog**

- Title: `Approve this migration plan?`
- Body: `Approval records your name and the time. It does not notify owners automatically.`
- Actions: **Cancel**, **Approve report**.

---

## 12. `/reports/[reportId]/print`

**Purpose:** printable/shareable artifact.

**Layout:** A4, 20 mm margins, paper palette, no navigation.

- Cover block: product, report title, diff, date, owner counts.
- Summary.
- Changes.
- Affected services table.
- Migration waves.
- Cycle warnings.
- Footer: graph version, engine version, page numbers.

**Rules:** page-break-inside avoid for wave blocks; severity colors darken for print; readable in grayscale.

---

## 13. `/bindings` — Consumer bindings

**Purpose:** correct gaps in the dependency graph.

**Layout**

- Header: `Consumer bindings`, primary **Add binding**.
- Toolbar: provider filter, consumer filter, source filter.
- Table: Provider, Consumer, Endpoint, Source, Confidence, Evidence, Created.
- Inferred rows show a dashed source chip and a **Confirm** action (converts to manual).
- Manual rows have **Remove**.

**Add binding dialog**

- Fields: provider service, consumer service, method, path.
- Copy: `Use this when a consumer cannot declare x-consumers. Manual bindings are treated as certain.`
- Validation: path must start with `/`; provider and consumer must differ.

**States**

- Empty: `No bindings yet. Declare one or add x-consumers to a spec.`

---

## 14. `/settings/tokens` — CI tokens

**Purpose:** create and revoke pipeline credentials.

**Layout**

- Header: `CI tokens`, primary **Create token**.
- Table: Name, Prefix, Scopes, Last used, Created, Status, Actions.
- Create dialog: name, scope checkboxes (`specs:write`, `webhooks:write`, `reports:read`), **Create token**.
- On success: reveal-once modal with the plaintext and Copy.

**States**

- Empty: `No CI tokens. Create one to publish specs from your pipeline.`
- Revoked: neutral chip `Revoked`, no actions.
- Copy failure: `Copy failed. Select the token manually.`

**Security copy:** `Store this token now. It cannot be shown again.`

---

## 15. `/settings/webhooks` — Webhook events and DLQ

**Purpose:** verify CI integration and recover failures.

**Layout**

- Header: `Webhooks`.
- Tabs: Events, Dead letter queue.
- Events table: Event, Type, Idempotency key, Signature, Status, Received, Processed, Error.
- DLQ table: Job type, Error, Retries, Last attempt, Actions (`Replay`, `Discard`).
- Event detail drawer: payload (session only), linked spec version, diff, report, trace ID.

**States**

- Empty events: `No webhook events yet. POST to /api/v1/webhooks/ci from your pipeline.`
- Empty DLQ: `No failed jobs.`
- Replay success: toast `Job replayed.`
- Replay conflict: `This job was already replayed.`

---

## 16. Global states

### 404

`Page not found.` → **Back to overview**

### Session expired

Banner on the next navigation: `Your session expired. Sign in again.` → **Sign in** (preserves `next`).

### Offline

Top banner: `You are offline. Changes will not save.`

### Unexpected error

Full-page panel: `Something went wrong.` `Reference: <trace_id>` → **Retry** / **Back to overview**

---

## 17. Flow diagrams

### Upload → graph

```text
/login → / → /specs → /specs/upload
  → Step 1 service → Step 2 document → Step 3 review
  → POST /specs → /specs/[id]/graph
  → graph builds → nodes render
```

### Diff → report

```text
/specs/[id]/versions → select two → Compare
  → /specs/[id]/diff?from&to → poll status
  → review critical changes
  → Generate impact report → /reports/[id]
  → poll → review waves → Approve → Print
```

### Webhook → report

```text
CI → POST /webhooks/ci → 202
/settings/webhooks → event processed
/reports → new report → /reports/[id]
```

---

## 18. Screen-level non-goals

- No dark/light toggle.
- No dashboard customization or drag-and-drop widgets.
- No in-browser spec editor.
- No comments, mentions, or notifications inbox.
- No organization or member management.
- No graph editing beyond consumer bindings.
