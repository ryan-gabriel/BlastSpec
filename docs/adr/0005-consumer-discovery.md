# ADR-0005: Consumer discovery precedence

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Product, Architecture
- **Related:** `DATA_MODEL.md`, `GRAPH_ALGORITHMS.md`, `PRD.md`

## Context

The project plan defines `Consumer` nodes but never says how the system learns that a service consumes an endpoint. Without an explicit rule, impact analysis has no reliable input and the demo cannot be reproduced.

## Decision

Consumer bindings are discovered from three sources, in precedence order:

1. **Declared** — the provider's spec includes `x-consumers` (string or object form, see `DATA_MODEL.md`). Confidence `1.00`. Source `declared`.
2. **Inferred** — another registered service has a `base_url` matching a `servers[].url` host, or a spec path/operation matches a known endpoint. Confidence `0.60`. Source `inferred`.
3. **Manual** — a user declares a binding in the UI/API. Confidence `1.00`. Source `manual`.

Rules:

- Higher precedence wins for the same `(provider, consumer, method, path)` tuple.
- All bindings are stored in Postgres and are the input to graph rebuilds.
- The UI shows provenance and confidence; inferred edges are dashed.
- `DEPENDS_ON` is derived from bindings: `consumer DEPENDS_ON provider`.
- A report MUST state when it includes only inferred bindings, because coverage may be incomplete.

## Consequences

### Positive

- Reproducible demo and tests.
- Honest about uncertainty instead of pretending the graph is complete.
- Manual override covers the long tail.

### Negative

- Inference can create false edges; confidence and provenance mitigate this.
- Users must annotate specs or bindings for best results.
- Host matching can be wrong behind shared gateways.

## Alternatives considered

- **Only declared metadata:** too much authoring burden.
- **Only runtime traffic:** out of scope and privacy-sensitive.
- **Only manual:** does not demo graph construction.
