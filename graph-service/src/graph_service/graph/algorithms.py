from __future__ import annotations

from collections import deque
from typing import Any


def reverse_reachability(
    workspace_id: str,
    endpoint_ids: list[str],
    direct_consumers: dict[str, list[str]],
    depends_on_reverse: dict[str, list[str]],
    endpoint_to_provider: dict[str, str],
    max_depth: int = 0,
) -> list[dict[str, Any]]:
    """Compute all affected services from a set of changed endpoints.

    direct_consumers: endpoint_id -> list of consumer service_ids
    depends_on_reverse: service_id -> list of service_ids that depend on it (reverse edges)
    endpoint_to_provider: endpoint_id -> provider service_id
    """
    affected: dict[str, dict[str, Any]] = {}
    direct_consumer_ids: set[str] = set()

    # Provider at depth 0
    for eid in endpoint_ids:
        provider = endpoint_to_provider.get(eid)
        if provider and provider not in affected:
            affected[provider] = {
                "service_id": provider,
                "relation": "provider",
                "depth": 0,
                "endpoint_ids": [eid],
                "path": [provider],
            }

    # Direct consumers at depth 1
    for eid in endpoint_ids:
        for consumer_sid in direct_consumers.get(eid, []):
            direct_consumer_ids.add(consumer_sid)
            if consumer_sid not in affected:
                affected[consumer_sid] = {
                    "service_id": consumer_sid,
                    "relation": "direct_consumer",
                    "depth": 1,
                    "endpoint_ids": [eid],
                    "path": [endpoint_to_provider.get(eid, ""), consumer_sid],
                }
            else:
                # Already present (e.g. as provider) — keep lower depth, but mark direct
                if affected[consumer_sid]["depth"] > 1:
                    affected[consumer_sid]["depth"] = 1
                    affected[consumer_sid]["relation"] = "direct_consumer"
                affected[consumer_sid]["endpoint_ids"] = list(
                    set(affected[consumer_sid]["endpoint_ids"] + [eid])
                )

    # BFS for transitive consumers
    queue: deque[tuple[str, int]] = deque()
    for sid, info in affected.items():
        queue.append((sid, info["depth"]))

    visited: set[str] = set(affected.keys())

    while queue:
        current, depth = queue.popleft()
        if max_depth and depth >= max_depth:
            continue
        for dependent in depends_on_reverse.get(current, []):
            next_depth = depth + 1
            if dependent in affected:
                if next_depth < affected[dependent]["depth"]:
                    affected[dependent]["depth"] = next_depth
                    if dependent in direct_consumer_ids:
                        affected[dependent]["relation"] = "direct_consumer"
                continue
            affected[dependent] = {
                "service_id": dependent,
                "relation": "transitive_consumer",
                "depth": next_depth,
                "endpoint_ids": [],
                "path": affected[current]["path"] + [dependent],
            }
            visited.add(dependent)
            queue.append((dependent, next_depth))

    # Reclassify: direct consumers that were reached transitively keep direct_consumer
    for sid in direct_consumer_ids:
        if sid in affected:
            affected[sid]["relation"] = "direct_consumer"

    return list(affected.values())


def tarjan_scc(nodes: list[str], edges: dict[str, list[str]]) -> list[list[str]]:
    """Tarjan's strongly connected components."""
    index_counter = [0]
    stack: list[str] = []
    on_stack: set[str] = set()
    indices: dict[str, int] = {}
    lowlink: dict[str, int] = {}
    components: list[list[str]] = []

    import sys
    sys.setrecursionlimit(10000)

    def strongconnect(v: str) -> None:
        indices[v] = lowlink[v] = index_counter[0]
        index_counter[0] += 1
        stack.append(v)
        on_stack.add(v)

        for w in sorted(edges.get(v, [])):
            if w not in indices:
                strongconnect(w)
                lowlink[v] = min(lowlink[v], lowlink[w])
            elif w in on_stack:
                lowlink[v] = min(lowlink[v], indices[w])

        if lowlink[v] == indices[v]:
            component: list[str] = []
            while True:
                w = stack.pop()
                on_stack.discard(w)
                component.append(w)
                if w == v:
                    break
            components.append(sorted(component))

    for v in sorted(nodes):
        if v not in indices:
            strongconnect(v)

    return components


def kahn_topological_order(
    nodes: list[str],
    edges: dict[str, list[str]],
    node_key: dict[str, tuple[str, str, str]],
) -> tuple[list[str], list[list[str]], list[list[str]]]:
    """Kahn's algorithm with SCC condensation.

    edges: dependent -> list of dependencies (dependent DEPENDS_ON dependency)
    node_key: node_id -> (owner_team, name, id) for sorting

    Returns: (order, waves, cycles)
    """
    # Step 1: Find SCCs
    reverse_edges: dict[str, list[str]] = {n: [] for n in nodes}
    for dep, dependents in edges.items():
        for d in dependents:
            reverse_edges.setdefault(d, []).append(dep)

    # Build adjacency for Tarjan (node -> dependencies)
    tarjan_edges = {n: list(edges.get(n, [])) for n in nodes}
    sccs = tarjan_scc(nodes, tarjan_edges)

    # Map node to component index
    node_to_comp: dict[str, int] = {}
    for i, comp in enumerate(sccs):
        for node in comp:
            node_to_comp[node] = i

    # Identify cycles: SCC with >1 node or self-loop
    cycle_components: set[int] = set()
    for i, comp in enumerate(sccs):
        if len(comp) > 1:
            cycle_components.add(i)
        elif len(comp) == 1:
            node = comp[0]
            if node in edges.get(node, []):
                cycle_components.add(i)

    # Build component DAG
    comp_nodes = list(range(len(sccs)))
    comp_edges: set[tuple[int, int]] = set()
    for dep, dependents in edges.items():
        for dependent in dependents:
            c_dep = node_to_comp[dep]
            c_dependent = node_to_comp[dependent]
            if c_dep != c_dependent:
                comp_edges.add((c_dep, c_dependent))

    # Kahn's on component DAG
    in_degree = {c: 0 for c in comp_nodes}
    successors: dict[int, list[int]] = {c: [] for c in comp_nodes}
    for dep_comp, dependent_comp in comp_edges:
        successors[dep_comp].append(dependent_comp)
        in_degree[dependent_comp] += 1

    def comp_sort_key(c: int) -> tuple[str, str, str]:
        comp = sccs[c]
        if len(comp) == 1:
            return node_key.get(comp[0], ("", "", comp[0]))
        # For cycle group, use lexicographically smallest
        keys = [node_key.get(n, ("", "", n)) for n in comp]
        return min(keys)

    # Use a priority queue (min-heap) via sorted list
    ready = sorted([c for c in comp_nodes if in_degree[c] == 0], key=comp_sort_key)
    ordered_comps: list[int] = []

    while ready:
        comp = ready.pop(0)
        ordered_comps.append(comp)
        for nxt in sorted(successors[comp], key=comp_sort_key):
            in_degree[nxt] -= 1
            if in_degree[nxt] == 0:
                ready.append(nxt)
                ready.sort(key=comp_sort_key)

    if len(ordered_comps) != len(comp_nodes):
        # Should not happen after SCC condensation
        pass

    # Expand to service order (skip cycle components)
    order: list[str] = []
    for comp in ordered_comps:
        if comp in cycle_components:
            continue
        for node in sccs[comp]:
            order.append(node)

    # Compute waves
    comp_wave: dict[int, int] = {}
    # Topological order for waves
    in_deg = {c: 0 for c in comp_nodes}
    succ = {c: [] for c in comp_nodes}
    for dep_comp, dependent_comp in comp_edges:
        succ[dep_comp].append(dependent_comp)
        in_deg[dependent_comp] += 1

    wave_queue = [c for c in comp_nodes if in_deg[c] == 0]
    for c in wave_queue:
        comp_wave[c] = 0

    # Process in topological order
    processed = set()
    while wave_queue:
        current = wave_queue.pop(0)
        if current in processed:
            continue
        processed.add(current)
        for nxt in succ[current]:
            comp_wave[nxt] = max(comp_wave.get(nxt, 0), comp_wave[current] + 1)
            in_deg[nxt] -= 1
            if in_deg[nxt] == 0:
                wave_queue.append(nxt)

    # Build waves list (service_ids per wave)
    max_wave = max(comp_wave.values()) if comp_wave else 0
    waves_map: dict[int, list[str]] = {i: [] for i in range(max_wave + 1)}
    for comp, w in comp_wave.items():
        for node in sccs[comp]:
            waves_map[w].append(node)

    waves = [{"index": i, "service_ids": sorted(waves_map[i])} for i in range(max_wave + 1) if waves_map[i]]

    # Cycles
    cycles: list[list[str]] = []
    for comp in cycle_components:
        cycles.append(sccs[comp])

    # Strategy
    total_nodes = len(nodes)
    cycle_nodes = sum(len(sccs[c]) for c in cycle_components)
    if cycle_nodes == 0:
        strategy = "topological"
    elif cycle_nodes == total_nodes:
        strategy = "coordinated"
    else:
        strategy = "mixed"

    return order, waves, cycles, strategy
