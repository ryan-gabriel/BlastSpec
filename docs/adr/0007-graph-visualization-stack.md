# ADR-0007: Graph visualization stack

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Design, Frontend
- **Related:** `DESIGN.md`, `docs/SCREENS.md`

## Context

The graph must show services, endpoints, and consumer relationships; support selection, focus, zoom, and severity highlighting; and remain usable for non-graph users. The plan lists Next.js and Tailwind but not a graph library or layout.

## Decision

1. Render with `@xyflow/react` (React Flow) for nodes, edges, pan/zoom, and custom node components.
2. Lay out with `elkjs` in `layered` mode, left-to-right: providers on the left, consumers on the right.
3. Inferred edges are dashed; declared/manual edges are solid. Severity is a halo plus a labeled chip, never color alone.
4. Cap the rendered graph at 500 nodes; above that, switch to an aggregated view and require filtering.
5. Provide a list/table fallback with identical data, keyboard navigation, and export.
6. Persist viewport per spec in local storage only; no server-side layout state.

## Consequences

### Positive

- Mature interaction model and accessible focus handling.
- ELK produces readable dependency layers.
- Custom nodes match the design system.
- List fallback satisfies accessibility and large-graph limits.

### Negative

- ELK layout is CPU-heavy for large graphs; mitigated by the node cap and web worker.
- React Flow state must be kept out of server components.
- Another client dependency to audit.

## Alternatives considered

- **Cytoscape.js:** powerful but heavier styling work.
- **D3 force layout:** less deterministic and harder to make accessible.
- **Server-rendered SVG:** poor interaction and zoom.
