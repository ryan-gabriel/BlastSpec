<?php

// Security audit checklist — verified during implementation
// See SECURITY.md §14 for the full PR checklist

// Upload safety: SpecLimits enforces 5MB, depth 64, 200k nodes, 100 aliases, no remote $ref
// Auth: session cookie HTTP-only, SameSite=Lax, CSRF on mutating requests
// Tokens: SHA-256 hash, prefix lookup, constant-time compare, scopes enforced
// Webhook: HMAC over raw body, timestamp window 300s, idempotency key
// Cypher: parameterized queries only (repository.py)
// XSS: React escapes by default, no dangerouslySetInnerHTML
// CORS: explicit origins, no wildcard with credentials
// Rate limits: configured per surface
// Secrets: .env not committed, redaction in logs
// Dependencies: lockfiles committed, audits in CI
