# ADR-0008: Demo session auth and hashed CI tokens

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Product, Security
- **Related:** `SECURITY.md`, `API_CONTRACT.md`, `DATA_MODEL.md`

## Context

The portfolio demo needs a usable UI and a credible automation path without spending the project on organization management, SSO, and billing. The plan's API includes a CI webhook but no auth model.

## Decision

1. **UI:** one seeded demo workspace and user; email/password login; Laravel session cookie; no registration.
2. **CI:** hashed bearer tokens with scopes (`specs:write`, `webhooks:write`, `reports:read`); plaintext shown once.
3. **Webhook:** requires both the bearer token and an HMAC signature.
4. **Tenancy:** every table carries `workspace_id`, and every query is scoped. Organizations, invitations, and roles are deferred.
5. **Approval:** only session users can approve reports; tokens cannot.

## Consequences

### Positive

- Demo is immediately usable with seeded credentials.
- CI integration is realistic and secure enough to discuss.
- `workspace_id` avoids a painful migration when orgs are added.
- Scope model is small and testable.

### Negative

- No self-service onboarding.
- Single workspace limits the multi-tenant story.
- Demo credentials are public by design; the deployment must be treated as non-sensitive.

## Alternatives considered

- **Full orgs/roles:** scope creep; not needed to prove the core thesis.
- **No auth:** weakens the security narrative and the webhook demo.
- **OAuth only:** adds provider setup friction for reviewers.
