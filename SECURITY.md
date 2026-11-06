---
status: approved
owner: Security
last_verified: 2026-11-02
source_of_truth_for:
  - threat model
  - upload safety
  - credential handling
related:
  - API_CONTRACT.md
  - ARCHITECTURE.md
  - DEPLOYMENT.md
  - RULES.md
---

# Security

## 1. Assets

| Asset | Why it matters |
|---|---|
| Spec content | May contain internal hostnames, schema details, and unreleased API design |
| Consumer bindings | Reveals internal architecture and ownership |
| CI tokens | Allow writing specs and triggering analysis |
| Webhook secret | Allows forged pipeline events |
| Postgres / Redis / Kuzu | Full domain data |
| Session cookies | UI access to the demo workspace |
| Internal graph token | Full graph read/write |

## 2. Trust boundaries

```text
Browser  --public-->  Laravel API  --private-->  Graph service  -->  Kuzu volume
CI       --public-->  Laravel API
Laravel  --private-->  Postgres / Redis
Workers  --private-->  Graph service
```

Everything crossing a boundary is untrusted: uploaded files, headers, query strings, webhook bodies, internal responses.

## 3. Threat model (STRIDE-lite)

| Threat | Vector | Control |
|---|---|---|
| Spoofing | Forged CI webhook | Bearer token + HMAC + timestamp window |
| Spoofing | Stolen session cookie | HTTP-only, Secure, SameSite=Lax, short lifetime |
| Tampering | Modified webhook body | HMAC over raw body before parsing |
| Repudiation | "We never received the event" | `webhook_events` audit with signature status |
| Information disclosure | Cross-workspace IDOR | `workspace_id` on every query; `404` for foreign IDs |
| Information disclosure | Token in logs | Redaction; plaintext returned once |
| Denial of service | 5 GB upload / YAML bomb | Size, depth, alias, and time limits |
| Denial of service | Graph query storm | Rate limits, query timeouts, result caps |
| Elevation of privilege | Token without scope | Scope check on every mutating route |
| Injection | Cypher string interpolation | Parameterized queries only |
| Injection | Stored XSS in spec text | Render as text; sanitize any markdown |
| SSRF | Remote `$ref` or server URL fetch | Never fetch remote references or URLs |
| Supply chain | Malicious dependency | Lockfiles, audits, pinned images |

## 4. Upload safety

Uploads are the highest-risk input. The parser MUST enforce all of the following before any document is stored or parsed.

### 4.1 Pre-parse limits

| Limit | Value | Failure |
|---|---|---|
| Raw file size | 5 MB | `413 ERR-1203` |
| File extension | `.json`, `.yaml`, `.yml` | `415 ERR-1106` |
| Detected format | JSON object or YAML mapping | `422 ERR-1201` |
| Parse wall time | 5 seconds | `422 ERR-1201` |
| Nesting depth | 64 | `422 ERR-1201` |
| Total nodes | 200,000 | `422 ERR-1201` |
| YAML aliases | 100 references, no recursive aliases | `422 ERR-1201` |
| `$ref` hops | 32 | `422 ERR-1204` |
| `$ref` count | 1,000 | `422 ERR-1204` |
| Endpoints | 5,000 per spec | `422 ERR-1201` |

### 4.2 Parser rules

- JSON: `json_decode` with depth 64, reject invalid UTF-8.
- YAML: Symfony YAML with object parsing disabled and custom tags rejected. Do not use `unserialize`, `eval`, or object hydration from document keys.
- Detect format by content, not extension; JSON must decode to an object.
- `$ref` resolution is **local only** (`#/components/...`). Any `http://`, `https://`, `file://`, or absolute path reference returns `ERR-1204`.
- Never fetch `servers[].url`, `externalDocs.url`, or link targets.
- Reject documents whose `openapi` field is not `3.0.x` or `3.1.x` (`ERR-1202`).
- Enforce the endpoint and schema caps during canonicalization, not after.
- Parsing runs in the request with a hard timeout; if it exceeds 5 s, abort and discard.
- Raw content is stored only after parsing succeeds far enough to be a valid OpenAPI object. Parse failures may store a `parse_failed` version for audit only if it passed the size/type limits; it is never used for graph or diff.

### 4.3 Rendering

- Spec titles, descriptions, paths, and schema values are rendered as **text**, not HTML.
- If markdown is rendered, it is sanitized with an allowlist (no raw HTML, no `javascript:` links).
- Graph node labels are escaped by React; never use `dangerouslySetInnerHTML`.

## 5. Authentication and authorization

### 5.1 Sessions

- Cookie: `blastspec_session`, HTTP-only, `Secure` in production, `SameSite=Lax`.
- Session lifetime: 8 hours idle, 7 days absolute.
- CSRF: Laravel token, `X-XSRF-TOKEN` header for mutating requests.
- Login rate limit: 5 attempts per minute per IP; generic failure message.
- No registration endpoint. The demo user is seeded.

### 5.2 CI tokens

- Plaintext: `bs_<8 hex prefix>_<32-byte random secret, hex>`.
- Store `prefix` (lookup) and `sha256(secret)` (verification). Never store plaintext.
- Compare hashes with `hash_equals`.
- Scopes are checked per route; missing scope returns `403 ERR-1002`.
- Revocation is immediate (`revoked_at`); cached tokens are not trusted beyond 60 s.
- `last_used_at` is updated at most once per minute to avoid write amplification.
- Tokens are displayed exactly once and never returned by list/get endpoints.
- A token cannot approve reports in MVP.

### 5.3 Workspace isolation

- Every domain table has `workspace_id`.
- Policies and query scopes MUST filter by the authenticated workspace.
- Foreign resource IDs return `404`, not `403`.
- Cross-workspace joins are forbidden.
- Integration tests MUST include a "foreign ID returns 404" case for every resource.

## 6. Webhook security

- Verify the bearer token and scope before reading the body.
- Read the **raw body**, verify HMAC, then decode JSON.
- Canonical string: `<timestamp> + "." + <raw body>`.
- HMAC-SHA256, lowercase hex, header `X-BlastSpec-Signature: v1=<hex>`.
- Timestamp skew ≤ 300 seconds; reject older or future timestamps.
- Constant-time comparison (`hash_equals`).
- Idempotency key scoped to workspace; store body hash to detect key reuse with different content.
- Body limit: 5 MB.
- Replay protection is provided by the timestamp window plus idempotency.
- Secret rotation: accept the current secret and the previous secret for 24 hours, then remove the old one.

## 7. Internal graph service

- Bound to a private network; never published in production.
- `X-Internal-Token` compared in constant time.
- Token is at least 32 random bytes, distinct from `APP_KEY` and `WEBHOOK_SECRET`.
- Request body limit 10 MB; query timeout 5 seconds; result cap 50,000 nodes.
- Cypher queries accept parameters only. No identifier interpolation.
- Kuzu path is writable only by the graph service user; container runs as non-root.
- Graph responses are validated by Laravel before persistence; unknown fields are ignored.

## 8. Secrets

| Secret | Storage | Rotation |
|---|---|---|
| `APP_KEY` | Env/secret store | Re-encrypt on rotation |
| `GRAPH_SERVICE_TOKEN` | Env/secret store | Restart both services |
| `WEBHOOK_SECRET` | Env/secret store | Dual-secret window, 24 h |
| DB credentials | Env/secret store | Provider-managed |
| Redis credentials | Env/secret store | Provider-managed |
| CI token plaintext | User only | Revoke + recreate |

Rules:

- `.env` is never committed; `.env.example` contains placeholders only.
- No secret in logs, error bodies, traces, or analytics.
- Redact: `Authorization`, `Cookie`, `X-BlastSpec-Signature`, `X-Internal-Token`, `password`, `plaintext`, `token_hash`.
- CI runs a secret scanner (gitleaks) on every PR.
- Demo deployment uses distinct secrets from local.

## 9. HTTP hardening

| Header | Value |
|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (production) |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Content-Security-Policy` | `default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self' https://api.blastspec.dev; frame-ancestors 'none'` |
| `X-Frame-Options` | `DENY` |

- CORS allows only `CORS_ALLOWED_ORIGINS`, with credentials for the UI origin.
- No wildcard origin with credentials.
- API responses set `Cache-Control: no-store` for authenticated data.

## 10. Rate limiting and abuse

- Login: 5/min/IP.
- Uploads: 10/min/workspace.
- Webhook: 60/min/token.
- Graph queries: 120/min/user; 240/min/token.
- Graph response size capped; `depth` capped at 10.
- Impact analysis concurrency: one active report per workspace; additional requests queue.

## 11. Logging and monitoring

Log structured JSON with:

- `trace_id`, `workspace_id`, `user_id` or `token_prefix`, `route`, `status`, `duration_ms`.
- Job logs: `job_id`, `job_type`, `attempt`, `aggregate_id`.
- Graph logs: `graph_version`, `node_count`, `edge_count`, `duration_ms`.

Never log:

- raw spec content (log `content_hash` and `bytes` instead)
- full URLs with query strings if they might contain tokens
- passwords, token plaintext, HMAC values, cookies

Alert-worthy events:

- repeated `401` on webhooks
- signature failures above baseline
- DLQ growth
- graph sync failures
- parse timeouts

## 12. Dependencies and containers

- Composer, npm, and Python lockfiles are committed.
- CI runs `composer audit`, `npm audit --audit-level=high`, and `pip-audit`.
- Container images are pinned by digest in production.
- Containers run as non-root; root filesystem is read-only except the Kuzu volume and temp directory.
- No `latest` tags in deployment manifests.
- Dependabot/Renovate updates are reviewed weekly.

## 13. Incident response (demo scope)

1. Revoke affected CI tokens.
2. Rotate `WEBHOOK_SECRET` with the dual-secret window.
3. Rotate `GRAPH_SERVICE_TOKEN` and restart both services.
4. Rebuild the graph from Postgres.
5. Review `webhook_events`, `outbox`, and logs by `trace_id`.
6. Document the incident and the fix in an ADR if structural.

## 14. Security checklist (PR)

- [ ] No secret, token, or password added to code, tests, or fixtures.
- [ ] New endpoint has auth, scope, workspace scoping, and rate limit.
- [ ] New upload or parse path enforces size/depth/ref limits.
- [ ] New Cypher uses parameters only.
- [ ] New user-rendered string is escaped or sanitized.
- [ ] New dependency is justified and audited.
- [ ] Error responses contain no internal detail.
- [ ] Foreign-workspace access returns `404`.
- [ ] Logs contain no sensitive payloads.
