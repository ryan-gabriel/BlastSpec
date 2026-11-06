---
status: approved
owner: API
last_verified: 2026-11-02
source_of_truth_for:
  - API conventions
  - error catalog
  - webhook signing
  - internal graph API
related:
  - contracts/openapi.yaml
  - ARCHITECTURE.md
  - SECURITY.md
  - DATA_MODEL.md
---

# API Contract

`contracts/openapi.yaml` is the canonical machine-readable contract. This document explains the conventions, errors, and internal endpoints that the OpenAPI file cannot express well.

When this document and `contracts/openapi.yaml` disagree, the OpenAPI file wins for HTTP shape and this document wins for semantics and error codes. Fix both in the same PR.

---

## 1. Conventions

| Concern | Rule |
|---|---|
| Base path | `/api/v1` |
| Encoding | JSON (`application/json`); uploads use `multipart/form-data` |
| Field naming | `snake_case` in all request/response bodies |
| IDs | UUIDv7 strings |
| Timestamps | ISO-8601 UTC (`2026-11-02T14:03:21Z`) |
| Nulls | Explicit `null`; omitted fields are not equivalent to null in PATCH |
| Versioning | Path version. Breaking changes require `/api/v2` |
| Pagination | Cursor-based: `?cursor=&limit=`; response `{data, pagination:{next_cursor, has_more}}` |
| Sorting | Fixed per endpoint; newest first unless documented |
| Filtering | Query parameters only; unknown parameters are ignored |
| Correlation | Every response includes `X-Request-Id`; clients MAY send one |
| Idempotency | `Idempotency-Key` required on webhooks, optional on other POSTs |
| Rate limits | Per credential; `429` includes `Retry-After` |
| CORS | Allowed origins from `CORS_ALLOWED_ORIGINS`; credentials allowed for the UI origin |

### 1.1 Content types

- Requests with a body MUST send `Content-Type`.
- Unsupported types return `415 ERR-1106`.
- Uploads accept `.json`, `.yaml`, `.yml` and `application/json`, `application/yaml`, `text/yaml`. Anything else returns `415 ERR-1106`.
- Max upload size is **5 MB**; larger returns `413 ERR-1203`.

### 1.2 Status codes

| Code | Meaning |
|---|---|
| `200` | Sync success |
| `201` | Resource created |
| `202` | Async work accepted; poll the resource |
| `204` | Success with no body |
| `400` | Malformed request |
| `401` | Missing/invalid credentials or signature |
| `403` | Authenticated but missing scope |
| `404` | Not found in this workspace |
| `409` | Duplicate, conflicting state, or idempotency mismatch |
| `413` | Upload too large |
| `415` | Unsupported media type |
| `422` | Validation or spec parsing failed |
| `429` | Rate limited |
| `500` | Unhandled server error |
| `503` | Dependency unavailable |

---

## 2. Authentication and authorization

### 2.1 UI sessions

- `POST /auth/login` sets `blastspec_session` (HTTP-only, `SameSite=Lax`, `Secure` in production).
- Mutating requests from the browser MUST include `X-XSRF-TOKEN` (Laravel cookie/header pair).
- Sessions are scoped to the demo workspace.

### 2.2 CI tokens

- Header: `Authorization: Bearer bs_<prefix>_<secret>`.
- The server looks up by `prefix`, then verifies `sha256(secret)` in constant time.
- Tokens are revoked by setting `revoked_at`; revoked tokens return `401 ERR-1003`.
- Scopes:

| Scope | Grants |
|---|---|
| `specs:write` | Upload specs and versions, trigger graph rebuild |
| `webhooks:write` | Post the CI webhook |
| `reports:read` | Read diffs, changes, reports |
| `reports:write` (future) | Create/approve reports; not issued in MVP |

The webhook requires both a valid bearer token with `webhooks:write` **and** a valid HMAC signature.

### 2.3 Workspace isolation

Every query filters by the authenticated `workspace_id`. A resource ID from another workspace returns `404`, never `403`, to avoid existence leaks.

---

## 3. Errors

All errors use RFC 9457 `application/problem+json`:

```json
{
  "type": "https://blastspec.dev/errors/ERR-1204",
  "title": "Spec could not be parsed",
  "status": 422,
  "detail": "Unresolvable $ref at /paths/~1users/get/responses/200/content/application~1json/schema",
  "code": "ERR-1204",
  "trace_id": "4f1c2a9e8b7d4c3a",
  "errors": [
    {
      "pointer": "/paths/~1users/get/responses/200",
      "message": "Reference #/components/schemas/Missing does not exist",
      "code": "ref_unresolved"
    }
  ]
}
```

Rules:

- `code` is stable and documented below.
- `detail` is safe for end users: no stack traces, SQL, secrets, or internal hostnames.
- `errors[]` is present for `422` and optional elsewhere.
- `trace_id` matches `X-Request-Id`.

### 3.1 Error catalog

| Code | HTTP | Meaning | Retryable | User action |
|---|---|---|---|---|
| `ERR-1001` | 401 | Missing or invalid credentials | No | Sign in or provide a valid token |
| `ERR-1002` | 403 | Missing scope or access | No | Use a token with the required scope |
| `ERR-1003` | 401 | Token revoked or expired | No | Create a new token |
| `ERR-1004` | 419 | CSRF token missing or expired | Yes | Refresh the page and retry |
| `ERR-1101` | 422 | Request validation failed | No | Fix the listed fields |
| `ERR-1102` | 404 | Resource not found | No | Check the ID |
| `ERR-1103` | 409 | State conflict | No | Refresh and retry |
| `ERR-1104` | 409 | Idempotency key reused with a different body | No | Use a new key |
| `ERR-1105` | 429 | Rate limit exceeded | Yes | Wait for `Retry-After` |
| `ERR-1106` | 415 | Unsupported media type | No | Send JSON/YAML as documented |
| `ERR-1107` | 413 | Payload too large | No | Upload ≤ 5 MB |
| `ERR-1201` | 422 | Spec parse failed | No | Fix the OpenAPI document |
| `ERR-1202` | 422 | Unsupported OpenAPI version | No | Use 3.0.x or 3.1.x |
| `ERR-1203` | 413 | Spec exceeds size limit | No | Reduce the document |
| `ERR-1204` | 422 | `$ref` unresolved or remote | No | Inline or define the component |
| `ERR-1205` | 409 | Duplicate spec version or content | No | Use a new version label/content |
| `ERR-1206` | 422 | Service owner team is required | No | Set `owner_team` |
| `ERR-1207` | 409 | Spec is referenced by an active report | No | Archive the report first |
| `ERR-1301` | 409 | Diff is not completed | Yes | Poll until `completed` |
| `ERR-1302` | 422 | Versions belong to different specs | No | Use two versions of one spec |
| `ERR-1303` | 422 | No data to diff | No | Choose different versions |
| `ERR-1304` | 500 | Diff processing failed | Yes | Retry the diff; inspect server logs |
| `ERR-1401` | 503 | Graph service unavailable | Yes | Retry after recovery |
| `ERR-1402` | 503 | Graph is stale for this diff | Yes | Rebuild the graph, then retry |
| `ERR-1403` | 500 | Graph sync failed | Yes | Trigger a rebuild |
| `ERR-1404` | 404 | Endpoint not found in the graph | No | Rebuild the graph |
| `ERR-1405` | 422 | Impact analysis produced no affected services | No | Confirm consumer bindings exist |
| `ERR-1406` | 422 | Migration order unavailable | No | Resolve the reported cycle manually |
| `ERR-1501` | 401 | Webhook signature invalid | No | Fix the signing secret or payload |
| `ERR-1502` | 401 | Webhook timestamp outside the 5-minute window | Yes | Send a fresh timestamp |
| `ERR-1503` | 409 | Idempotency key already used with different content | No | Use a new key |
| `ERR-1504` | 422 | Unknown webhook event type | No | Use `spec.published` |
| `ERR-1601` | 409 | Impact report is not completed | Yes | Poll until `completed` |
| `ERR-1602` | 409 | Impact report already approved | No | None |
| `ERR-1603` | 500 | Impact analysis failed | Yes | Retry or inspect the DLQ |
| `ERR-9001` | 500 | Internal error | Yes | Retry; report the `trace_id` |

Adding an error code requires updating this table, the OpenAPI descriptions, and frontend copy.

---

## 4. Public endpoints

Full request/response schemas live in `contracts/openapi.yaml`. This section records behavior that is easy to miss.

### 4.1 Specs

**`POST /specs`** (multipart)

- Validates size/type, then parses and canonicalizes synchronously.
- Creates a `specs` row, the first `spec_versions` row, and an outbox event in one transaction.
- Returns `201 SpecDetail` with `current_version`.
- A parse failure returns `422` and creates **no** spec or version.

**`POST /specs/{id}/versions`**

- Creates an immutable version.
- Duplicate `(spec_id, version)` or `(spec_id, content_hash)` returns `409 ERR-1205`.
- On success, updates `current_version_id` only if the new version is the latest by `created_at`.
- Queues a graph sync.

**`DELETE /specs/{id}`**

- Soft delete. Returns `204`.
- Returns `409 ERR-1207` if an active (non-approved, non-archived) report references the spec.
- Deleted specs are excluded from graph builds.

**`GET /specs/{id}/graph`**

- `version_id` defaults to the current version.
- `stale` is `true` when the latest completed graph build does not include `version_id`.
- Returns `503 ERR-1401` when the graph service is unreachable.

### 4.2 Diffs

**`POST /diffs`**

- Both versions MUST belong to `spec_id`; otherwise `422 ERR-1302`.
- Identical version IDs return `422 ERR-1303`.
- Existing identical diff returns the existing resource with `202` (idempotent read of an in-flight/completed diff).
- Poll `GET /diffs/{id}` until `completed` or `failed`.

**`GET /diffs/{id}/changes`**

- Supports `severity`, `breaking`, `method`, `path`, cursor pagination.
- Ordered by severity (`critical` first), then path, then method, then fingerprint.

### 4.3 Impact reports

**`POST /impact-reports`**

- Requires a `completed` diff; otherwise `409 ERR-1301`.
- `change_ids` defaults to all `breaking=true` changes.
- If none of the selected changes are breaking, the report is still created and labeled `strategy=topological` with `affected_services` limited to the provider. This is intentional: teams may want an informational report.
- Requires a completed graph build that includes the diff's `to_version_id`; otherwise `503 ERR-1402`.

**`POST /impact-reports/{id}/approve`**

- Only `completed` reports can be approved (`409 ERR-1601`).
- Repeat approval returns `409 ERR-1602`.
- Records `approved_by` and `approved_at`.

### 4.4 Tokens

**`POST /tokens`**

- Returns `plaintext` exactly once.
- The plaintext format is `bs_<prefix>_<secret>`; `prefix` is 8 hex characters.
- Scopes are validated against the enum.
- The UI MUST display the plaintext in a reveal-once dialog and MUST NOT log it.

### 4.5 Webhook events

**`GET /webhook-events`**

- Read-only audit view.
- `payload` is returned only to session-authenticated users, never to token-authenticated requests.

---

## 5. CI webhook

### 5.1 Request

```http
POST /api/v1/webhooks/ci HTTP/1.1
Authorization: Bearer bs_9f2c1a4b_2f7c...
Content-Type: application/json
X-BlastSpec-Timestamp: 1762092201
X-BlastSpec-Signature: v1=5f2c...
Idempotency-Key: ci-build-4821
```

```json
{
  "event_type": "spec.published",
  "spec_id": "0192f3c4-1111-7abc-8def-0123456789ab",
  "version": "2.0.0",
  "spec": { "openapi": "3.1.0", "info": {}, "paths": {} },
  "analyze_impact": true
}
```

### 5.2 Signing

Canonical string:

```text
<X-BlastSpec-Timestamp> + "." + <raw request body bytes>
```

Signature:

```text
X-BlastSpec-Signature: v1=<lowercase hex HMAC-SHA256(webhook_secret, canonical_string)>
```

Verification rules:

1. `X-BlastSpec-Timestamp` is Unix seconds or ISO-8601 UTC.
2. Absolute skew MUST be ≤ 300 seconds; otherwise `401 ERR-1502`.
3. Recompute the HMAC using the workspace's current `WEBHOOK_SECRET`.
4. Compare in constant time; mismatch returns `401 ERR-1501`.
5. Record the event with `signature_status=valid|invalid|expired`.

The raw body MUST be read before JSON decoding; re-encoding changes the bytes and breaks the signature.

### 5.3 Idempotency

- `Idempotency-Key` is required, 8–128 characters, scoped to workspace.
- First request: process and store a hash of the body.
- Same key + same body hash: return `202` with `duplicate: true` and the original resource IDs.
- Same key + different body hash: `409 ERR-1503`.

### 5.4 Processing

1. Reject invalid signature/timestamp before writing anything.
2. Insert `webhook_events` row + `spec_versions` row + outbox event in one transaction.
3. Return `202` with `event_id` and `status: "queued"`.
4. Worker parses, creates a diff against the current version, and, if `analyze_impact` and critical changes exist, creates a report.
5. `webhook_events.status` becomes `processed` or `failed` with `error`.

---

## 6. Internal graph service API

Not publicly routed. Requires `X-Internal-Token` and network isolation. All requests are JSON. Errors use the same problem envelope with `ERR-14xx` codes.

### 6.1 `POST /internal/graph/sync`

```json
{
  "workspace_id": "uuid",
  "snapshot_hash": "sha256",
  "services": [
    { "id": "uuid", "name": "billing", "slug": "billing", "owner_team": "Payments" }
  ],
  "endpoints": [
    {
      "id": "uuid",
      "provider_service_id": "uuid",
      "spec_version_id": "uuid",
      "key": "GET /users/{id}",
      "method": "GET",
      "path": "/users/{id}",
      "operation_id": "getUser",
      "summary": "Get a user"
    }
  ],
  "schema_components": [
    { "id": "uuid", "provider_service_id": "uuid", "key": "User", "type": "object", "format": null, "required": ["id"], "enum_values": null }
  ],
  "consumer_bindings": [
    {
      "provider_service_id": "uuid",
      "consumer_service_id": "uuid",
      "method": "GET",
      "path": "/users/{id}",
      "source": "declared",
      "confidence": 1.0
    }
  ]
}
```

Response:

```json
{ "graph_version": "sha256", "nodes": 42, "edges": 71, "duration_ms": 83 }
```

- The service recomputes the snapshot hash; mismatch returns `409 ERR-1403`.
- The sync is transactional and idempotent.

### 6.2 `GET /internal/graph/workspace/{workspace_id}`

Query: `version_id?`, `depth?`, `include_schemas?`.

Returns the same `GraphResponse` shape as the public endpoint, including `graph_version`, `stale`, `warnings`.

### 6.3 `POST /internal/graph/impact`

```json
{
  "workspace_id": "uuid",
  "endpoint_ids": ["uuid"],
  "max_depth": 0
}
```

Response:

```json
{
  "graph_version": "sha256",
  "affected": [
    {
      "service_id": "uuid",
      "relation": "direct_consumer",
      "depth": 1,
      "endpoint_ids": ["uuid"],
      "path": ["billing", "checkout"]
    }
  ],
  "warnings": []
}
```

- `max_depth = 0` means unlimited.
- Results are deduplicated per service using the minimum depth.
- A service that is both a direct and transitive consumer is reported as `direct_consumer`.

### 6.4 `POST /internal/graph/migration-order`

```json
{
  "workspace_id": "uuid",
  "service_ids": ["uuid", "uuid", "uuid"]
}
```

Response:

```json
{
  "graph_version": "sha256",
  "strategy": "topological",
  "order": ["uuid", "uuid", "uuid"],
  "waves": [
    { "index": 0, "service_ids": ["uuid"] },
    { "index": 1, "service_ids": ["uuid", "uuid"] }
  ],
  "cycles": []
}
```

If a cycle exists, `strategy` is `coordinated` or `mixed`, `order` contains only acyclic members, and `cycles[]` lists each SCC with `service_ids`, `service_names`, and `recommended_break_service_id`. See `GRAPH_ALGORITHMS.md`.

### 6.5 `GET /internal/health`

```json
{
  "status": "ok",
  "kuzu": { "open": true, "path_writable": true },
  "graph_version": "sha256",
  "uptime_s": 1204
}
```

Returns `503` when Kuzu cannot be opened or the path is read-only.

---

## 7. Rate limits

| Surface | Limit |
|---|---|
| Session requests | 120 per minute per user |
| Token requests | 240 per minute per token |
| Uploads | 10 per minute per workspace |
| Webhook | 60 per minute per token |
| Login | 5 per minute per IP |

Exceeding a limit returns `429 ERR-1105` with `Retry-After` seconds.

## 8. Deprecation

- A deprecated field or endpoint returns `Deprecation` and `Sunset` headers.
- Removal requires at least one minor version of overlap.
- Because v1 is the only version, no deprecations ship in MVP.
