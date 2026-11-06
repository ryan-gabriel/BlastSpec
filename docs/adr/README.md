# Architecture Decision Records

ADRs capture decisions that are expensive to reverse. Implementation details belong in the owning document; ADRs explain **why**.

| ID | Decision | Status |
|---|---|---|
| [0001](0001-contract-first-api.md) | Contract-first HTTP API | Accepted |
| [0002](0002-kuzu-embedded-derived-store.md) | Kuzu is embedded and derived | Accepted |
| [0003](0003-postgres-system-of-record-outbox.md) | Postgres system of record + outbox | Accepted |
| [0004](0004-redis-streams-consumer-groups.md) | Redis Streams with consumer groups | Accepted |
| [0005](0005-consumer-discovery.md) | Consumer discovery precedence | Accepted |
| [0006](0006-change-fingerprints.md) | Stable change fingerprints | Accepted |
| [0007](0007-graph-visualization-stack.md) | Graph visualization stack | Accepted |
| [0008](0008-demo-auth-and-ci-tokens.md) | Demo auth and hashed CI tokens | Accepted |

## When to write an ADR

Write one when a decision:

- changes a public contract, schema, or data ownership boundary
- introduces or removes infrastructure
- cannot be undone without a migration or a rewrite
- resolves a contradiction between documents

Do not write one for naming, formatting, or local refactors.

## Template

```md
# ADR-000N: Title

- **Status:** Proposed | Accepted | Superseded
- **Date:** YYYY-MM-DD
- **Deciders:** roles
- **Related:** docs

## Context
What forces are at play?

## Decision
What we will do.

## Consequences
### Positive
### Negative

## Alternatives considered
What else was evaluated and why it lost.
```
