---
status: approved
owner: Product
last_verified: 2026-11-02
source_of_truth_for:
  - domain terminology
related:
  - PRD.md
  - DATA_MODEL.md
  - GRAPH_ALGORITHMS.md
  - BREAKING_CHANGES.md
---

# Glossary

Canonical vocabulary for BlastSpec. Code, UI copy, tests, and docs MUST use these terms. When a term conflicts with a synonym, this file wins.

## Core terms

| Term | Definition | Do not confuse with |
|---|---|---|
| **Workspace** | The top-level isolation boundary. MVP ships one seeded demo workspace, but every row carries `workspace_id`. | User, organization |
| **Service** | A deployable system that either provides or consumes APIs. Has a name, slug, owner team, optional repo URL, and optional base URL. | Spec, repository |
| **Provider** | The service that owns and publishes an API contract. | Consumer |
| **Consumer** | A service that calls an endpoint owned by a provider. | Client, user |
| **Consumer binding** | A durable record stating that `consumer_service` calls `METHOD /path` on `provider_service`, with a source and confidence. | Graph edge (the graph edge is derived from the binding) |
| **Owner team** | The team accountable for a service and therefore notified about breaking changes. Required before an impact report can be generated. | Author, user |
| **Spec** | An OpenAPI document registered for a service. The spec is the container; versions hold the content. | Spec version |
| **Spec version** | One immutable uploaded revision of a spec, identified by a human label plus a content hash. | Spec |
| **Endpoint key** | Canonical identifier for an operation: uppercase method, single space, normalized path. Example: `GET /users/{id}`. | URL |
| **Canonicalization** | Deterministic transformation of an OpenAPI document into the normalized form used for diffing and graph building. | Validation, linting |
| **Contract** | The observable request/response surface of an endpoint, including parameters, bodies, status codes, and security. | Implementation, docs |
| **Contract change** | One detected difference between two spec versions, identified by a stable fingerprint and a `BR-###` rule. | Breaking change (a severity subset) |
| **Breaking change** | A contract change with severity `critical` that can break an existing consumer. | Any change |
| **Severity** | One of `critical`, `warning`, `info`. Definitions are normative in `BREAKING_CHANGES.md`. | Priority |
| **Diff** | The computed set of contract changes between a from-version and a to-version. Persisted as `contract_diffs`. | Impact report |
| **Impact report** | A persisted analysis of a diff: affected services, owners to notify, migration waves, and evidence. | Diff |
| **Affected service** | A service that is the provider or a direct/transitive consumer of a changed endpoint. | Any service in the workspace |
| **Migration order** | A deterministic ordering of affected services where dependencies are migrated before their dependents. | Migration wave |
| **Migration wave** | A group of services that can migrate in parallel. Wave 0 has no dependencies inside the affected set. | Migration order |
| **Dependency** | A directed relationship where service A requires service B (`A DEPENDS_ON B`). A cannot be migrated before B. | Consumer binding (bindings create dependencies) |
| **Reverse reachability** | Graph traversal from a changed endpoint or provider to all direct and transitive consumers. | Forward reachability |
| **Graph version** | Content hash of all graph inputs at build time. Used to detect stale graph reads. | Spec version |
| **Graph rebuild** | Full recomputation of the Kuzu graph from Postgres records. The graph is always rebuildable. | Incremental upsert |
| **Outbox** | A Postgres table holding domain events written in the same transaction as the state change, relayed asynchronously. | Queue, DLQ |
| **Relay** | The worker process that reads pending outbox rows and publishes them to Redis Streams. | Consumer |
| **Dead letter queue (DLQ)** | Storage for jobs that exhausted retries, with enough context to replay. | Outbox |
| **Job** | A unit of asynchronous work identified by a UUID and tracked through a resource status. | Task (agent backlog item) |
| **Idempotency key** | Caller-supplied key that makes a mutating request safe to retry. Scoped to workspace + route. | Request ID |
| **Correlation ID** | `X-Request-Id` propagated through HTTP, jobs, and logs for tracing. | Idempotency key |
| **CI token** | A hashed bearer credential used by pipelines. Format `bs_<prefix>_<secret>`; plaintext shown once. | Session |
| **Webhook event** | An inbound CI call recorded for audit, deduplication, and replay. | Outbox event |
| **Demo workspace** | The single seeded workspace that ships with the app. | Tenant |

## Status vocabularies

| Entity | Allowed statuses |
|---|---|
| Spec version | `parsed`, `parse_failed` |
| Diff | `queued`, `running`, `completed`, `failed` |
| Impact report | `queued`, `running`, `completed`, `failed`, `approved` |
| Webhook event | `received`, `queued`, `processed`, `failed`, `duplicate` |
| Outbox row | `pending`, `processing`, `processed`, `failed` |
| DLQ job | `pending`, `replayed`, `discarded` |

## Naming rules

- UI and docs say **impact report**, never "impact analysis report".
- UI and docs say **service**, never "system" or "app" when referring to graph nodes.
- "Consumer" always means a service, never an end user.
- "Breaking" always means severity `critical`; use "warning" and "info" explicitly otherwise.
- Severity values are lowercase in JSON and code, title case in UI (`Critical`).
