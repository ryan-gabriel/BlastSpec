# ADR-0003: Postgres system of record with a transactional outbox

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Architecture, Data
- **Related:** `ARCHITECTURE.md`, `DATA_MODEL.md`

## Context

Spec uploads, graph syncs, diffs, and reports span multiple stores. Publishing to Redis inside a request is unsafe: the transaction can commit after the publish, or roll back after it. The project plan requires the outbox pattern as a demonstrated capability.

## Decision

1. Postgres owns every domain entity and every job outcome.
2. A state change and its `outbox` row are written in the same transaction.
3. `outbox:relay` claims rows with `FOR UPDATE SKIP LOCKED`, publishes to Redis Streams, and marks them processed.
4. Handlers are idempotent; at-least-once delivery is acceptable.
5. Redis is transport, never the source of truth.

## Consequences

### Positive

- No lost events on crash between commit and publish.
- Reliable retries with a durable audit trail.
- Jobs can be reconstructed from Postgres.
- Demonstrates a real reliability pattern.

### Negative

- Duplicate delivery is possible and must be handled.
- Relay adds a moving part and a small latency.
- Outbox table needs pruning.

## Alternatives considered

- **Publish directly in the request:** rejected; dual-write failure.
- **Postgres LISTEN/NOTIFY:** no durability or consumer groups.
- **Kafka:** overkill for the demo and not free-tier friendly.
