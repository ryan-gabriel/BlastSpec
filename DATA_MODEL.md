---
status: approved
owner: Data
last_verified: 2026-11-02
source_of_truth_for:
  - Postgres schema
  - Kuzu graph schema
  - canonical normalized spec shape
related:
  - ARCHITECTURE.md
  - GRAPH_ALGORITHMS.md
  - BREAKING_CHANGES.md
  - API_CONTRACT.md
---

# Data Model

Two stores:

- **PostgreSQL** — system of record. All tables below live here.
- **Kuzu** — derived graph. Fully rebuildable from Postgres. Never the only copy of anything.

All IDs are **UUIDv7** strings unless noted. All timestamps are `timestamptz` in UTC. JSON columns are `jsonb`.

---

## 1. Postgres schema

### 1.1 `workspaces`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `name` | text | not null |
| `slug` | text | not null, unique |
| `created_at` | timestamptz | not null, default now() |

MVP seeds exactly one row. Every other table carries `workspace_id` so isolation is enforceable now and organizations can be added later.

### 1.2 `users`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `name` | text | not null |
| `email` | text | not null, unique |
| `password_hash` | text | not null |
| `created_at` | timestamptz | not null |

Demo credentials are seeded; registration is not exposed.

### 1.3 `api_tokens`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `user_id` | uuid | FK → users, null (system tokens) |
| `name` | text | not null |
| `prefix` | text | not null, unique, 12–16 chars |
| `token_hash` | char(64) | not null, unique |
| `scopes` | text[] | not null, subset of `specs:write`, `webhooks:write`, `reports:read` |
| `last_used_at` | timestamptz | null |
| `revoked_at` | timestamptz | null |
| `created_at` | timestamptz | not null |

`token_hash = sha256(secret_part)` where the plaintext is `bs_<prefix>_<secret_part>`. The plaintext is never stored.

### 1.4 `services`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `name` | text | not null |
| `slug` | text | not null |
| `owner_team` | text | not null, non-empty |
| `repo_url` | text | null |
| `base_url` | text | null |
| `deleted_at` | timestamptz | null |
| `created_at` | timestamptz | not null |
| `updated_at` | timestamptz | not null |

Unique: `(workspace_id, slug)`. Index: `(workspace_id, owner_team)`.

### 1.5 `specs`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `service_id` | uuid | FK → services, not null |
| `name` | text | not null |
| `slug` | text | not null |
| `current_version_id` | uuid | FK → spec_versions, null, deferrable |
| `deleted_at` | timestamptz | null |
| `created_at` | timestamptz | not null |
| `updated_at` | timestamptz | not null |

Unique: `(service_id, slug)`. The circular FK is added after `spec_versions` exists.

### 1.6 `spec_versions`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `spec_id` | uuid | FK → specs, not null |
| `version` | text | not null, 1–40 chars |
| `raw_content` | text | not null |
| `content_hash` | char(64) | not null, sha256 of raw bytes |
| `openapi_version` | text | null |
| `normalized` | jsonb | null until parsed |
| `parse_status` | text | not null, `parsed` or `parse_failed` |
| `parse_error` | text | null |
| `warnings` | jsonb | not null, default `[]` |
| `endpoint_count` | integer | not null, default 0 |
| `graph_version` | text | null, last graph build containing this version |
| `created_by` | uuid | FK → users, null |
| `created_at` | timestamptz | not null |

Unique: `(spec_id, version)`, `(spec_id, content_hash)`. Index: `(spec_id, created_at desc)`.

Immutable: no `updated_at`; rows are never edited after insert except `graph_version`.

### 1.7 `graph_builds`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `status` | text | `queued`, `running`, `completed`, `failed` |
| `input_hash` | char(64) | not null |
| `graph_version` | text | null |
| `error` | text | null |
| `created_at` | timestamptz | not null |
| `started_at` | timestamptz | null |
| `completed_at` | timestamptz | null |

Index: `(workspace_id, created_at desc)`.

`graph_build_versions` join table:

| Column | Type | Constraints |
|---|---|---|
| `graph_build_id` | uuid | FK → graph_builds |
| `spec_version_id` | uuid | FK → spec_versions |

PK: `(graph_build_id, spec_version_id)`.

### 1.8 `contract_diffs`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `spec_id` | uuid | FK → specs, not null |
| `from_version_id` | uuid | FK → spec_versions, not null |
| `to_version_id` | uuid | FK → spec_versions, not null |
| `status` | text | `queued`, `running`, `completed`, `failed` |
| `engine_version` | text | not null |
| `summary` | jsonb | not null, default `{critical:0,warning:0,info:0,total:0,breaking:false}` |
| `error` | text | null |
| `created_at` | timestamptz | not null |
| `started_at` | timestamptz | null |
| `completed_at` | timestamptz | null |

Unique: `(spec_id, from_version_id, to_version_id)`. Index: `(workspace_id, created_at desc)`.

### 1.9 `contract_changes`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `diff_id` | uuid | FK → contract_diffs, not null, on delete cascade |
| `fingerprint` | char(64) | not null |
| `br_rule_id` | text | not null, `BR-###` |
| `kind` | text | not null, machine name |
| `severity` | text | `critical`, `warning`, `info` |
| `breaking` | boolean | not null |
| `method` | text | null |
| `path` | text | null |
| `pointer` | text | null, RFC 6901 |
| `before` | jsonb | null |
| `after` | jsonb | null |
| `description` | text | not null |
| `created_at` | timestamptz | not null |

Unique: `(diff_id, fingerprint)`. Index: `(diff_id, severity)`, `(diff_id, breaking)`.

`breaking = (severity = 'critical')` is an invariant enforced by a check constraint.

### 1.10 `impact_reports`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `diff_id` | uuid | FK → contract_diffs, not null |
| `status` | text | `queued`, `running`, `completed`, `failed`, `approved` |
| `strategy` | text | `topological`, `coordinated`, `mixed` |
| `graph_version` | text | null |
| `summary` | jsonb | not null |
| `error` | text | null |
| `created_by` | uuid | FK → users, null |
| `approved_by` | uuid | FK → users, null |
| `approved_at` | timestamptz | null |
| `created_at` | timestamptz | not null |
| `started_at` | timestamptz | null |
| `completed_at` | timestamptz | null |

Index: `(workspace_id, created_at desc)`, `(diff_id)`.

### 1.11 `impact_report_items`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `report_id` | uuid | FK → impact_reports, cascade |
| `service_id` | uuid | FK → services, not null |
| `owner_team` | text | not null |
| `relation` | text | `provider`, `direct_consumer`, `transitive_consumer` |
| `depth` | integer | not null, ≥ 0 |
| `change_ids` | uuid[] | not null |
| `reason` | text | not null |

Unique: `(report_id, service_id)`.

### 1.12 `impact_report_waves`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `report_id` | uuid | FK → impact_reports, cascade |
| `wave_index` | integer | not null, ≥ 0 |
| `service_id` | uuid | FK → services, not null |

Unique: `(report_id, service_id)`. Index: `(report_id, wave_index)`.

### 1.13 `impact_report_dependencies`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `report_id` | uuid | FK → impact_reports, cascade |
| `service_id` | uuid | FK → services, not null |
| `depends_on_service_id` | uuid | FK → services, not null |
| `in_cycle` | boolean | not null, default false |

Unique: `(report_id, service_id, depends_on_service_id)`.

### 1.14 `impact_report_cycles`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `report_id` | uuid | FK → impact_reports, cascade |
| `service_ids` | uuid[] | not null, length ≥ 2 |
| `recommended_break_service_id` | uuid | null |
| `reason` | text | not null |

### 1.15 `consumer_bindings`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `provider_service_id` | uuid | FK → services, not null |
| `consumer_service_id` | uuid | FK → services, not null |
| `method` | text | not null, HTTP method |
| `path` | text | not null, starts with `/` |
| `source` | text | `declared`, `inferred`, `manual` |
| `confidence` | numeric(3,2) | not null, 0.00–1.00 |
| `evidence` | jsonb | not null, default `{}` |
| `created_by` | uuid | FK → users, null |
| `created_at` | timestamptz | not null |
| `updated_at` | timestamptz | not null |

Unique: `(provider_service_id, consumer_service_id, method, path, source)`. Index: `(workspace_id, provider_service_id)`, `(workspace_id, consumer_service_id)`.

### 1.16 `webhook_events`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `idempotency_key` | text | not null |
| `event_type` | text | not null, `spec.published` |
| `payload` | jsonb | not null, raw body |
| `signature_status` | text | `valid`, `invalid`, `expired` |
| `status` | text | `received`, `queued`, `processed`, `failed`, `duplicate` |
| `spec_version_id` | uuid | FK → spec_versions, null |
| `diff_id` | uuid | FK → contract_diffs, null |
| `report_id` | uuid | FK → impact_reports, null |
| `error` | text | null |
| `received_at` | timestamptz | not null |
| `processed_at` | timestamptz | null |

Unique: `(workspace_id, idempotency_key)`. Index: `(workspace_id, received_at desc)`.

### 1.17 `outbox`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `event_type` | text | not null |
| `aggregate_type` | text | not null |
| `aggregate_id` | uuid | not null |
| `payload` | jsonb | not null |
| `status` | text | `pending`, `processing`, `processed`, `failed` |
| `attempts` | integer | not null, default 0 |
| `available_at` | timestamptz | not null, default now() |
| `processed_at` | timestamptz | null |
| `last_error` | text | null |
| `created_at` | timestamptz | not null |

Index: `(status, available_at)`.

### 1.18 `dlq_jobs`

| Column | Type | Constraints |
|---|---|---|
| `id` | uuid | PK |
| `workspace_id` | uuid | FK → workspaces, not null |
| `job_type` | text | not null |
| `payload` | jsonb | not null |
| `error` | text | not null |
| `retry_count` | integer | not null, default 3 |
| `status` | text | `pending`, `replayed`, `discarded` |
| `last_attempt_at` | timestamptz | not null |
| `replayed_at` | timestamptz | null |
| `created_at` | timestamptz | not null |

### 1.19 Framework tables

Laravel owns `sessions`, `cache`, `cache_locks`, `jobs` (if the queue driver is used for non-stream work), and `failed_jobs`. They are not part of the domain model and MUST NOT be queried by feature code.

---

## 2. Invariants

1. Every row with `workspace_id` belongs to exactly one workspace; cross-workspace references are forbidden.
2. `specs.current_version_id` MUST reference a version whose `spec_id` matches.
3. A `spec_version` is immutable after insert, except `graph_version`.
4. A `contract_diff` MUST reference two versions of the same spec.
5. `contract_changes.breaking` MUST equal `severity = 'critical'`.
6. `(diff_id, fingerprint)` is unique; re-running a diff is idempotent.
7. An `impact_report` MUST reference a completed diff.
8. A report cannot be `approved` unless it is `completed`.
9. `impact_report_items` contains at most one row per service.
10. Every service in a report MUST have a non-empty `owner_team`.
11. Every endpoint in a graph build MUST belong to a `parsed` version.
12. Soft-deleted specs and services are excluded from new graph builds.
13. A graph build MUST record every spec version included in `graph_build_versions`.

---

## 3. Normalized spec shape (`spec_versions.normalized`)

`normalized` is the only input to diffing and graph building. Raw content is retained for audit and re-parsing.

```json
{
  "schema_version": 1,
  "openapi": "3.1.0",
  "info": { "title": "Billing API", "version": "2.0.0" },
  "servers": [{ "url": "https://billing.internal.acme.dev" }],
  "consumers": [{ "service": "checkout", "source": "x-consumers" }],
  "endpoints": [
    {
      "key": "GET /users/{id}",
      "method": "GET",
      "path": "/users/{id}",
      "operation_id": "getUser",
      "summary": "Get a user",
      "tags": ["users"],
      "deprecated": false,
      "parameters": [
        {
          "name": "id",
          "in": "path",
          "required": true,
          "schema": { "type": "string", "format": "uuid" }
        }
      ],
      "request_body": null,
      "responses": {
        "200": {
          "description": "User",
          "headers": {},
          "content": {
            "application/json": {
              "schema_ref": "#/components/schemas/User",
              "schema": { "$ref": "#/components/schemas/User" }
            }
          }
        },
        "404": { "description": "Not found", "headers": {}, "content": {} }
      },
      "security": [{ "scheme": "bearerAuth", "scopes": [] }]
    }
  ],
  "components": {
    "schemas": {
      "User": {
        "type": "object",
        "required": ["id", "email"],
        "properties": {
          "id": { "type": "string", "format": "uuid" },
          "email": { "type": "string", "format": "email" },
          "role": { "type": "string", "enum": ["admin", "member"] }
        },
        "additionalProperties": false
      }
    }
  },
  "security_schemes": {
    "bearerAuth": { "type": "http", "scheme": "bearer" }
  }
}
```

### 3.1 Canonicalization rules

| Rule | Behavior |
|---|---|
| Paths | Leading `/`, collapse `//`, templated segments preserved as `{name}` |
| Endpoint key | `<METHOD> <path>` with a single space |
| Parameters | Sorted by `(in, name)` |
| Responses | Keys sorted numerically as strings |
| Required lists | Sorted lexicographically |
| Enums | Sorted lexicographically (order is not semantic) |
| Tags | Sorted |
| Object keys | Sorted recursively |
| Media types | Lowercase, parameters stripped |
| Nullable | OpenAPI 3.1 `type: [X, "null"]` becomes `type: X`, `nullable: true` |
| Local `$ref` | Resolved against the document's `components`; `schema_ref` retained for pointers |
| Remote `$ref` | Rejected with `ERR-1204` |
| Vendor extensions | Dropped except `x-consumers` and `x-owner-team` |
| Descriptions | Retained; changes produce `info` rules only |
| Default values | Retained and compared |

### 3.2 Consumer declarations

`x-consumers` accepts either:

```yaml
x-consumers:
  - checkout
  - notifications
```

or:

```yaml
x-consumers:
  - service: checkout
    method: GET
    path: /users/{id}
    confidence: 1.0
```

A string entry declares the service consumes every operation in the document. The object form scopes the binding to one endpoint.

---

## 4. Kuzu graph schema

Kuzu is embedded in the graph service. The database directory is configured by `KUZU_PATH` and lives on a persistent volume.

### 4.1 Node tables

```cypher
CREATE NODE TABLE Service(
  id STRING,
  workspace_id STRING,
  name STRING,
  slug STRING,
  owner_team STRING,
  PRIMARY KEY (id)
);

CREATE NODE TABLE APIEndpoint(
  id STRING,
  workspace_id STRING,
  key STRING,
  method STRING,
  path STRING,
  operation_id STRING,
  summary STRING,
  spec_version_id STRING,
  PRIMARY KEY (id)
);

CREATE NODE TABLE SchemaComponent(
  id STRING,
  workspace_id STRING,
  key STRING,
  type STRING,
  format STRING,
  required BOOLEAN,
  enum_values STRING,
  PRIMARY KEY (id)
);

CREATE NODE TABLE Consumer(
  id STRING,
  workspace_id STRING,
  consumer_service_id STRING,
  source STRING,
  confidence DOUBLE,
  PRIMARY KEY (id)
);

CREATE NODE TABLE GraphMeta(
  id STRING,
  workspace_id STRING,
  graph_version STRING,
  built_at STRING,
  PRIMARY KEY (id)
);
```

### 4.2 Relationship tables

```cypher
CREATE REL TABLE PROVIDES(
  FROM Service TO APIEndpoint,
  spec_version_id STRING
);

CREATE REL TABLE CONSUMES(
  FROM Consumer TO APIEndpoint,
  source STRING,
  confidence DOUBLE
);

CREATE REL TABLE DEPENDS_ON(
  FROM Service TO Service,
  source STRING,
  confidence DOUBLE
);

CREATE REL TABLE REFERENCES(
  FROM APIEndpoint TO SchemaComponent,
  direction STRING
);
```

`DEPENDS_ON` means: the source service requires the target service. **Dependencies migrate first.**

### 4.3 Deterministic graph IDs

| Node | ID derivation |
|---|---|
| `Service` | Postgres `services.id` |
| `APIEndpoint` | `uuid5(NS, "<workspace_id>:<provider_service_id>:<METHOD> <path>")` |
| `SchemaComponent` | `uuid5(NS, "<provider_service_id>:<component_key>")` |
| `Consumer` | `uuid5(NS, "<consumer_service_id>:<provider_service_id>:<METHOD> <path>")` |
| `GraphMeta` | `uuid5(NS, "<workspace_id>:graph-meta")` |

### 4.4 Sync algorithm

1. Compute `graph_version` from the canonical snapshot.
2. `BEGIN TRANSACTION`.
3. Delete `GraphMeta`, all relationships, and all nodes where `workspace_id = $workspace_id`.
4. Insert services, endpoints, schema components, consumers.
5. Insert `PROVIDES`, `CONSUMES`, `DEPENDS_ON`, `REFERENCES`.
6. Upsert `GraphMeta` with `graph_version` and `built_at`.
7. `COMMIT`.

A failed sync rolls back and leaves the previous graph intact. Sync is serialized per workspace by an advisory lock in the graph service.

### 4.5 Graph version

```text
canonical_snapshot = {
  workspace_id,
  services:          sorted by id,
  endpoints:         sorted by key,
  schema_components: sorted by key,
  consumer_bindings: sorted by (provider_service_id, method, path, consumer_service_id, source)
}
graph_version = sha256(canonical_json(canonical_snapshot))
```

Laravel computes the hash before calling sync; the graph service recomputes and rejects a mismatch with `409`.

---

## 5. Query patterns

### Direct consumers of an endpoint

```cypher
MATCH (e:APIEndpoint {id: $endpoint_id})<-[:CONSUMES]-(c:Consumer)
RETURN DISTINCT c.consumer_service_id AS service_id,
       c.source AS source,
       c.confidence AS confidence
```

### Workspace services and dependencies

```cypher
MATCH (s:Service {workspace_id: $workspace_id})
OPTIONAL MATCH (s)-[d:DEPENDS_ON]->(dep:Service)
RETURN s.id, s.name, s.owner_team,
       collect(dep.id) AS dependency_ids
```

### Endpoints for a version

```cypher
MATCH (s:Service)-[:PROVIDES]->(e:APIEndpoint {spec_version_id: $version_id})
RETURN e.id, e.key, e.method, e.path
```

No query may interpolate user input into Cypher; parameters only.

---

## 6. Migrations

- Forward-only. A merged migration is never edited.
- Name: `YYYY_MM_DD_HHMMSS_verb_noun.php`.
- Every migration has a working `down()` for local rollback, even though production rolls forward.
- Destructive changes require a backfill plan and a two-step deploy.
- Schema changes require a matching update to this document in the same PR.
- CI compares `php artisan schema:dump` output with the committed schema snapshot.

## 7. Seed data

`php artisan db:seed --class=DemoSeeder` creates:

- one workspace (`demo`), one demo user
- services: `billing` (Payments), `checkout` (Storefront), `notifications` (Growth)
- billing spec v1 and v2 with at least one critical change
- consumer bindings: checkout → billing, notifications → billing
- one completed diff and one completed impact report
- one CI token for the demo pipeline

Seed data is idempotent and safe to re-run.

## 8. Retention

| Data | Retention |
|---|---|
| Spec versions and raw content | Indefinite (demo) |
| Diffs, changes, reports | Indefinite (demo) |
| Webhook events | 30 days |
| Outbox processed rows | 7 days |
| DLQ rows | 30 days after resolution |

Retention jobs are out of scope for MVP; the table documents intent.
