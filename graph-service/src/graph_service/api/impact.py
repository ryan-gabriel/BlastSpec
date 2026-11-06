from __future__ import annotations

from typing import Any

from fastapi import APIRouter, HTTPException, Request

from ..graph.algorithms import kahn_topological_order, reverse_reachability, tarjan_scc
from ..graph.repository import GraphRepository

router = APIRouter(prefix="/internal/graph", tags=["internal"])


def _get_repo(request: Request) -> GraphRepository:
    return request.app.state.repository


@router.post("/impact")
async def impact(request: Request) -> dict[str, Any]:
    repo = _get_repo(request)
    if not repo.is_open:
        raise HTTPException(status_code=503, detail="Graph service unavailable")

    body = await request.json()
    workspace_id = body["workspace_id"]
    endpoint_ids = body["endpoint_ids"]
    max_depth = body.get("max_depth", 0)

    # Get workspace graph
    graph = repo.get_workspace_graph(workspace_id)
    graph_version = graph.get("graph_version")

    # Build endpoint -> provider mapping
    endpoint_to_provider: dict[str, str] = {}
    for edge in graph["edges"]:
        if edge["type"] == "PROVIDES":
            endpoint_to_provider[edge["target"]] = edge["source"]

    # Build direct consumers: endpoint -> list of consumer service_ids
    # From CONSUMES edges: Consumer node -> endpoint; Consumer has consumer_service_id
    consumer_nodes = {n["id"]: n for n in graph["nodes"] if n["type"] == "Consumer"}
    direct_consumers: dict[str, list[str]] = {}
    for edge in graph["edges"]:
        if edge["type"] == "CONSUMES":
            consumer_node = consumer_nodes.get(edge["source"])
            if consumer_node:
                csid = consumer_node["data"].get("consumer_service_id") or consumer_node["label"]
                direct_consumers.setdefault(edge["target"], []).append(csid)

    # Build reverse dependency adjacency: service -> list of dependents
    depends_on_reverse: dict[str, list[str]] = {}
    for edge in graph["edges"]:
        if edge["type"] == "DEPENDS_ON":
            depends_on_reverse.setdefault(edge["target"], []).append(edge["source"])

    # Validate endpoints exist
    node_ids = {n["id"] for n in graph["nodes"]}
    for eid in endpoint_ids:
        if eid not in node_ids:
            raise HTTPException(status_code=404, detail=f"Endpoint {eid} not found in graph")

    affected = reverse_reachability(
        workspace_id=workspace_id,
        endpoint_ids=endpoint_ids,
        direct_consumers=direct_consumers,
        depends_on_reverse=depends_on_reverse,
        endpoint_to_provider=endpoint_to_provider,
        max_depth=max_depth,
    )

    # Classify relation properly
    direct_consumer_ids = set()
    for eid in endpoint_ids:
        for cs in direct_consumers.get(eid, []):
            direct_consumer_ids.add(cs)

    for item in affected:
        if item["service_id"] in direct_consumer_ids and item["relation"] != "provider":
            item["relation"] = "direct_consumer"

    return {
        "graph_version": graph_version,
        "affected": affected,
        "warnings": [],
    }
