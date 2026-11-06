---
status: approved
owner: Graph service
last_verified: 2026-11-02
source_of_truth_for:
  - impact analysis semantics
  - migration ordering
  - cycle handling
related:
  - DATA_MODEL.md
  - ARCHITECTURE.md
  - BREAKING_CHANGES.md
  - TESTING.md
---

# Graph Algorithms

All algorithms run in the FastAPI graph service over data held in Kuzu. Inputs are fetched with parameterized Cypher; traversal and ordering are implemented in Python for determinism and testability.

---

## 1. Graph semantics

### Node types

| Node | Meaning |
|---|---|
| `Service` | A deployable system with an owner team |
| `APIEndpoint` | One operation (`METHOD /path`) provided by a service |
| `SchemaComponent` | A named schema used by endpoints |
| `Consumer` | A reified consumer binding between two services and an endpoint |

### Edge direction

| Edge | Direction | Meaning |
|---|---|---|
| `PROVIDES` | `Service → APIEndpoint` | The service owns the endpoint |
| `CONSUMES` | `Consumer → APIEndpoint` | The consumer binding calls the endpoint |
| `DEPENDS_ON` | `Service → Service` | The source depends on the target; **target migrates first** |
| `REFERENCES` | `APIEndpoint → SchemaComponent` | The endpoint uses the schema |

`Consumer.consumer_service_id` points at the consuming `Service`. `DEPENDS_ON` is derived from bindings: for every binding `consumer → provider`, add `consumer DEPENDS_ON provider`.

### Definitions

- **Affected set** — the provider plus all services that transitively depend on it.
- **Direct consumer** — a service with a binding to the changed endpoint.
- **Transitive consumer** — a service that reaches a direct consumer through one or more `DEPENDS_ON` edges.
- **Depth** — shortest dependency distance from the changed provider; provider = 0, direct consumer = 1.
- **Wave** — the longest dependency path length from any wave-0 service; services in the same wave can migrate in parallel.
- **Cycle** — a strongly connected component with more than one service, or a self-loop. Cycles cannot be linearly ordered.

---

## 2. GRAPH-001 — Reverse reachability

**Input:** `workspace_id`, `endpoint_ids[]`, optional `max_depth` (0 = unlimited).

**Output:** affected services with relation, depth, endpoint evidence, and a path.

```python
def reverse_reachability(workspace_id, endpoint_ids, max_depth=0):
    # 1. Direct consumers of any changed endpoint
    direct = query("""
        MATCH (e:APIEndpoint {workspace_id: $ws})<-[:CONSUMES]-(c:Consumer)
        WHERE e.id IN $endpoint_ids
        RETURN DISTINCT c.consumer_service_id AS service_id,
               e.id AS endpoint_id
    """, ws=workspace_id, endpoint_ids=endpoint_ids)

    affected = {}  # service_id -> {depth, relation, endpoints, path}
    direct_services = set()
    for row in direct:
        direct_services.add(row.service_id)
        record(affected, row.service_id, depth=1, relation="direct_consumer",
               endpoint=row.endpoint_id, path=[provider_name, service_name])

    # 2. Reverse BFS over DEPENDS_ON: if X DEPENDS_ON Y and Y is affected,
    #    then X is affected at depth(Y) + 1.
    queue = deque((sid, 1) for sid in direct_services)
    while queue:
        current, depth = queue.popleft()
        if max_depth and depth >= max_depth:
            continue
        for dependent in reverse_dependencies(current):
            next_depth = depth + 1
            if update_min(affected, dependent, next_depth):
                queue.append((dependent, next_depth))

    return affected
```

Rules:

- Start from the endpoint's provider (`depth=0`, `relation=provider`) and include it in the report even if it has no consumers.
- A service that is both direct and transitive keeps `direct_consumer` and the smaller depth.
- Deduplicate by `service_id`.
- Exclude deleted services; add a warning.
- If an endpoint ID is not in the graph, return `ERR-1404`.
- If no consumers exist, return only the provider. This is not an error.

Complexity: `O(V + E)` for the BFS over the affected subgraph.

---

## 3. GRAPH-002 — Impact classification

After reachability, classify each service:

| Relation | Condition |
|---|---|
| `provider` | Owns a changed endpoint |
| `direct_consumer` | Has a `Consumer` binding to a changed endpoint |
| `transitive_consumer` | Reached through `DEPENDS_ON` only |

The report also records:

- `owner_team` from Postgres (the graph carries it, but Postgres is authoritative).
- `change_ids` that apply to the service.
- `reason`: a human sentence, for example `Consumes GET /users, which removed field email.`
- `path`: service names from provider to consumer (for evidence in the API, not necessarily in the UI).

Low-confidence inferred bindings are included but marked in `evidence`; the report summary notes when any binding is inferred.

---

## 4. GRAPH-003 — Topological migration order

**Input:** affected `service_ids[]`.

**Output:** linear order for acyclic services, waves, cycles, strategy.

Migration direction: **dependencies first**. If `A DEPENDS_ON B`, then `B` appears before `A`.

### 4.1 Build the induced dependency graph

```python
edges = query("""
    MATCH (s:Service {workspace_id: $ws})-[d:DEPENDS_ON]->(dep:Service)
    WHERE s.id IN $ids AND dep.id IN $ids
    RETURN s.id AS dependent, dep.id AS dependency, d.confidence AS confidence
""", ws=workspace_id, ids=service_ids)
```

Only edges where both ends are in the affected set participate.

### 4.2 Condense strongly connected components

Run Tarjan (GRAPH-004) first. Each SCC becomes a component:

- singleton without a self-loop → acyclic service
- larger SCC or self-loop → cycle group

Build a component DAG: for every edge `dependent → dependency`, add `component(dependency) → component(dependent)` unless both ends are in the same component.

### 4.3 Kahn's algorithm on the component DAG

```python
def kahn(components, component_edges):
    in_degree = {c: 0 for c in components}
    successors = {c: [] for c in components}

    for dependency, dependent in component_edges:
        successors[dependency].append(dependent)
        in_degree[dependent] += 1

    # Deterministic priority: owner_team, then name, then id.
    ready = MinHeap(c for c in components if in_degree[c] == 0)
    ordered = []

    while ready:
        component = ready.pop()
        ordered.append(component)
        for nxt in sorted(successors[component], key=component_sort_key):
            in_degree[nxt] -= 1
            if in_degree[nxt] == 0:
                ready.push(nxt)

    if len(ordered) != len(components):
        raise InternalError("component DAG still contains a cycle")
    return ordered
```

`component_sort_key = (owner_team, name, id)` of the component's representative service. For a cycle group, use the lexicographically smallest `(owner_team, name, id)` inside the group.

### 4.4 Expand to a service order

- Acyclic singleton components append their service ID.
- Cycle components are **not** appended to `order`; they appear in `cycles` and in their wave.

`strategy`:

| Condition | Strategy |
|---|---|
| No cycles | `topological` |
| Every affected service is in a cycle | `coordinated` |
| Both acyclic and cyclic services exist | `mixed` |

### 4.5 Determinism

The same input set and edges MUST produce the same order. Achieve this with:

- stable sort keys
- a min-heap (not a plain FIFO queue)
- canonical ordering of `service_ids` before processing

FIFO is only acceptable when the queue is populated from an already sorted list and successors are sorted; the heap removes ambiguity.

---

## 5. GRAPH-004 — Strongly connected components (Tarjan)

```python
def tarjan(nodes, edges):
    index = 0
    stack = []
    on_stack = set()
    indices = {}
    lowlink = {}
    components = []

    def strongconnect(v):
        nonlocal index
        indices[v] = lowlink[v] = index
        index += 1
        stack.append(v)
        on_stack.add(v)

        for w in sorted(edges.get(v, [])):
            if w not in indices:
                strongconnect(w)
                lowlink[v] = min(lowlink[v], lowlink[w])
            elif w in on_stack:
                lowlink[v] = min(lowlink[v], indices[w])

        if lowlink[v] == indices[v]:
            component = []
            while True:
                w = stack.pop()
                on_stack.remove(w)
                component.append(w)
                if w == v:
                    break
            components.append(sorted(component))

    for v in sorted(nodes):
        if v not in indices:
            strongconnect(v)

    return components
```

Recursion depth is a risk. Use an iterative Tarjan implementation in production, or raise the recursion limit with a hard cap.

### Cycle reporting

For each component with `len > 1` or a self-loop:

- `service_ids`, `service_names`
- `reason`: `These services depend on each other and cannot be migrated in a linear order.`
- `recommended_break_service_id`: the service whose outgoing dependency edge has the lowest confidence; tie-break by `(owner_team, name, id)`. If no confidence data exists, choose the lexicographically smallest service.

The report MUST NOT invent an order inside a cycle. It presents the group as a coordinated release.

---

## 6. GRAPH-005 — Migration waves

Waves group services that can migrate in parallel.

```python
def compute_waves(components, component_edges):
    in_degree = {c: 0 for c in components}
    deps = {c: [] for c in components}
    for dependency, dependent in component_edges:
        deps[dependent].append(dependency)
        in_degree[dependent] += 1

    wave = {}
    queue = [c for c in components if in_degree[c] == 0]
    for c in queue:
        wave[c] = 0

    while queue:
        current = queue.pop(0)
        for dependent in successors[current]:
            wave[dependent] = max(wave.get(dependent, 0), wave[current] + 1)
            in_degree[dependent] -= 1
            if in_degree[dependent] == 0:
                queue.append(dependent)

    return wave
```

Properties:

- A service with no dependencies inside the affected set is wave `0`.
- Provider services are usually wave `0`.
- If `A DEPENDS_ON B`, then `wave(A) > wave(B)` unless A and B are in the same SCC.
- All members of an SCC share the maximum wave of the component's dependencies plus one.
- Waves are contiguous from `0` to `max`; the API emits only non-empty waves.

---

## 7. GRAPH-006 — Graph build determinism

`graph_version` is the SHA-256 of a canonical snapshot (see `DATA_MODEL.md`). Requirements:

- Services sorted by ID.
- Endpoints sorted by key, then ID.
- Schema components sorted by key, then ID.
- Bindings sorted by `(provider_service_id, method, path, consumer_service_id, source)`.
- Consumer IDs derived with UUIDv5, never random.
- JSON serialized with sorted keys and no insignificant whitespace.
- Timestamps excluded from the hash.

The graph service recomputes the hash and returns `409 ERR-1403` on mismatch.

---

## 8. Worked example

Services and owners:

| Service | Owner |
|---|---|
| billing | Payments |
| checkout | Storefront |
| notifications | Growth |
| legacy | Payments |
| analytics | Data |

Dependencies (`A DEPENDS_ON B`):

```text
checkout      -> billing
notifications -> billing
legacy        -> billing
analytics     -> checkout
```

A critical change lands on `billing`'s `POST /charges`.

**Affected:**

| Service | Relation | Depth |
|---|---|---|
| billing | provider | 0 |
| checkout | direct_consumer | 1 |
| notifications | direct_consumer | 1 |
| legacy | direct_consumer | 1 |
| analytics | transitive_consumer | 2 |

**Waves:**

| Wave | Services |
|---|---|
| 0 | billing |
| 1 | checkout, legacy, notifications |
| 2 | analytics |

**Order** (sorted by owner, name within a wave):

```text
billing, notifications, legacy, checkout, analytics
```

`analytics` stays after `checkout` because of the dependency edge. The other wave-1 services have no order constraint.

**Cycle variant:** if `billing DEPENDS_ON analytics` and `analytics DEPENDS_ON checkout` and `checkout DEPENDS_ON billing`, then `{billing, checkout, analytics}` is an SCC:

- `strategy = mixed`
- `order = [notifications, legacy]` (wave 0/1 acyclic members)
- `waves = [{0: [notifications, legacy]}, {1: [billing, checkout, analytics]}]`
- `cycles = [{service_names: [analytics, billing, checkout], recommended_break_service_id: ...}]`

---

## 9. Complexity and limits

| Operation | Complexity | Limit |
|---|---|---|
| Reverse reachability | `O(V + E)` | 100k nodes, 500k edges |
| Tarjan SCC | `O(V + E)` | 100k nodes |
| Kahn order | `O((V + E) log V)` with heap | 100k nodes |
| Waves | `O(V + E)` | 100k nodes |
| Graph read | `O(V + E)` | 50k returned nodes |

- Query timeout: 5 seconds.
- Impact analysis timeout: 10 seconds.
- `max_depth` capped at 10.
- Above limits, return `ERR-1406` with guidance; do not silently truncate.

---

## 10. Edge cases

| Case | Behavior |
|---|---|
| No consumers | Report contains the provider only |
| Self-dependency | Treated as an SCC of size 1 with a self-loop; coordinated release |
| Duplicate bindings | Deduplicated by service; minimum depth wins |
| Provider also a consumer | Report once with `relation=provider`; dependency edges still apply |
| Orphan service | Not affected |
| Deleted service | Excluded; warning added |
| Missing owner team | Report creation fails `ERR-1206` (owner required) |
| Disconnected affected components | Waves computed independently, merged by index |
| Endpoint not in graph | `ERR-1404` |
| Graph stale | `ERR-1402`; rebuild first |
| All services in one cycle | `strategy=coordinated`, `order=[]` |
| Empty affected set | Not possible; provider is always included |

---

## 11. Invariants and tests

Property-based tests generate random DAGs and assert:

1. For every edge `dependency → dependent` in the affected set, `order.index(dependency) < order.index(dependent)`.
2. For every edge, `wave(dependent) > wave(dependency)` unless both are in the same SCC.
3. Every affected service appears exactly once in `items`.
4. Every service in a wave exists in the affected set.
5. No service appears in two waves.
6. `order` contains no cycle members.
7. Running twice on the same input yields identical output.
8. Reverse reachability is closed: if `X DEPENDS_ON Y` and `Y` is affected, `X` is affected.
9. A provider with no consumers yields exactly one item.
10. SCC detection agrees with a reference implementation (NetworkX in tests only).

Golden fixtures:

- `graph/linear.json`
- `graph/diamond.json`
- `graph/cycle.json`
- `graph/self-loop.json`
- `graph/disconnected.json`
- `graph/1000-endpoints.json`

See `TESTING.md` for commands and coverage gates.
