# ADR-0002: Kuzu is embedded and derived

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Architecture, Data
- **Related:** `ARCHITECTURE.md`, `DATA_MODEL.md`, `DEPLOYMENT.md`

## Context

The project plan showed a `kuzudb` container on port `7272`. Kuzu is an embedded graph database, like SQLite for graphs: it is opened by a process against a local directory. It is not a networked server. Treating it as a service would produce a broken deployment and an unclear ownership model.

## Decision

1. Kuzu runs **inside** the FastAPI graph service as a library.
2. The graph service is the only process that opens the Kuzu directory.
3. The directory lives on a persistent volume (`KUZU_PATH`).
4. Postgres is the system of record; Kuzu is derived and fully rebuildable.
5. There is no `kuzudb` container and no public port.
6. Graph sync is a transactional replace of the workspace subgraph.

## Consequences

### Positive

- Deployment matches how Kuzu actually works.
- Losing the graph volume is recoverable by rebuild.
- One writer removes lock contention and consistency ambiguity.
- Local setup is simpler: one fewer container.

### Negative

- Graph service becomes stateful and needs a volume.
- Horizontal scaling requires one graph service per workspace or a shared graph store.
- Full rebuilds are O(workspace size); acceptable at demo scale.

## Alternatives considered

- **Neo4j:** networked and mature, but heavier and not the stated stack.
- **Postgres recursive CTEs only:** simpler, but loses the graph-modeling signal.
- **Kuzu behind a custom server:** unnecessary; FastAPI already is the server.
