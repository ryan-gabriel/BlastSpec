from __future__ import annotations

from typing import Any

from fastapi import APIRouter, HTTPException, Request

from ..graph.algorithms import kahn_topological_order
from ..graph.repository import GraphRepository

router = APIRouter(prefix="/internal/graph", tags=["internal"])


def _get_repo(request: Request) -> GraphRepository:
    return request.app.state.repository


@router.post("/migration-order")
async def migration_order(request: Request) -> dict[str, Any]:
    repo = _get_repo(request)
    if not repo.is_open:
        raise HTTPException(status_code=503, detail="Graph service unavailable")

    body = await request.json()
    workspace_id = body["workspace_id"]
    service_ids = body["service_ids"]

    graph = repo.get_workspace_graph(workspace_id)
    graph_version = graph.get("graph_version")

    # Build dependency edges among affected services
    service_set = set(service_ids)
    edges: dict[str, list[str]] = {sid: [] for sid in service_ids}

    for edge in graph["edges"]:
        if edge["type"] == "DEPENDS_ON":
            if edge["source"] in service_set and edge["target"] in service_set:
                edges[edge["source"]].append(edge["target"])

    # Node key for sorting: (owner_team, name, id)
    node_info = {n["id"]: n for n in graph["nodes"] if n["type"] == "Service"}
    node_key: dict[str, tuple[str, str, str]] = {}
    for sid in service_ids:
        info = node_info.get(sid, {})
        data = info.get("data", {}) if isinstance(info, dict) else {}
        owner = data.get("owner_team", "") if isinstance(data, dict) else ""
        name = info.get("label", sid) if isinstance(info, dict) else sid
        node_key[sid] = (owner, name, sid)

    order, waves, cycles, strategy = kahn_topological_order(service_ids, edges, node_key)

    # Build cycle details
    cycle_details = []
    for cycle in cycles:
        cycle_details.append({
            "service_ids": cycle,
            "service_names": [node_info.get(s, {}).get("label", s) for s in cycle],
            "recommended_break_service_id": cycle[0] if cycle else None,
            "reason": "These services depend on each other and cannot be migrated in a linear order.",
        })

    return {
        "graph_version": graph_version,
        "strategy": strategy,
        "order": order,
        "waves": [{"index": w["index"], "service_ids": w["service_ids"]} for w in waves],
        "cycles": cycle_details,
    }
