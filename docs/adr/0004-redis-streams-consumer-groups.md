# ADR-0004: Redis Streams with consumer groups

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Architecture, Backend
- **Related:** `ARCHITECTURE.md`, `SECURITY.md`

## Context

Diffing, graph syncs, and impact analysis are CPU-bound and must not block HTTP requests. The project plan specifies Redis Streams, but Laravel's default Redis queue uses lists, not streams. The docs must state which mechanism is actually used.

## Decision

1. Use Redis Streams (`XADD`, `XREADGROUP`, `XACK`, `XAUTOCLAIM`) with one stream per job family:
   - `blastspec:graph`
   - `blastspec:diff`
   - `blastspec:impact`
   - `blastspec:webhook`
2. One consumer group per stream, named `workers`.
3. `XACK` only after the handler commits its Postgres changes.
4. Reclaim pending entries idle > 60 s with `XAUTOCLAIM`.
5. Retry at most 3 times with 2 s / 10 s / 30 s backoff, then write `dlq_jobs`.
6. Run consumers via `php artisan streams:consume <stream>` commands supervised by the worker container.

## Consequences

### Positive

- Durable pending-entry list with explicit acknowledgement.
- Multiple workers can share a stream safely.
- Demonstrates consumer groups, a stronger signal than a basic queue.
- DLQ replay is straightforward from Postgres.

### Negative

- More code than `dispatch(new Job)`.
- Laravel's queue UI/Horizon does not natively show stream state.
- Consumer crashes require reclaim logic (documented and tested).

## Alternatives considered

- **Laravel Redis queue (lists):** simpler, but does not demonstrate streams and has weaker pending semantics.
- **RabbitMQ:** another service, not in the stack.
- **Database queue:** no fan-out and higher Postgres load.
