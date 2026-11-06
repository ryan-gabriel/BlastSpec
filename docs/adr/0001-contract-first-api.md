# ADR-0001: Contract-first HTTP API

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Architecture, API
- **Related:** `contracts/openapi.yaml`, `API_CONTRACT.md`, `RULES.md`

## Context

BlastSpec is a product about API contracts. Its own API should demonstrate the discipline it sells. Hand-written frontend types and Laravel routes drift easily, especially when an agent implements endpoints in parallel.

## Decision

1. `contracts/openapi.yaml` is the canonical HTTP contract.
2. Frontend types are generated into `frontend/src/lib/api/schema.d.ts` with `openapi-typescript`.
3. Laravel feature tests validate real responses against the contract.
4. CI fails on generated-type drift or contract lint errors.
5. A contract change requires updating the YAML first, then code, then generated types.

## Consequences

### Positive

- One reviewable artifact describes every endpoint.
- Frontend and backend cannot silently diverge.
- Demonstrates contract governance, which is the product's thesis.
- Agents get a precise, machine-readable target.

### Negative

- Two-step changes (contract then code) add friction.
- Laravel does not generate the contract automatically; it is maintained by hand.
- Generated files must never be edited manually.

## Alternatives considered

- **Generate the contract from Laravel code:** weaker review surface and harder to design first.
- **TypeScript-first with Zod:** excludes non-TS consumers and hides the contract from CI tools.
- **No contract:** rejected; undermines the project's premise.
