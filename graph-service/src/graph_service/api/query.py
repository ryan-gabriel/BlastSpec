from __future__ import annotations

from fastapi import APIRouter, HTTPException, Request

from ..graph.repository import GraphRepository

router = APIRouter(prefix="/internal/graph", tags=["internal"])


def _get_repo(request: Request) -> GraphRepository:
    return request.app.state.repository


@router.get("/workspace/{workspace_id}")
async def get_workspace_graph(
    workspace_id: str,
    request: Request,
    version_id: str | None = None,
    depth: int = 0,
    include_schemas: bool = False,
) -> dict:
    repo = _get_repo(request)
    if not repo.is_open:
        raise HTTPException(status_code=503, detail="Graph service unavailable")

    result = repo.get_workspace_graph(workspace_id)

    # Determine staleness: check if latest graph build includes version_id
    # (Simplified: return graph_version; staleness determined by caller)
    graph_version = result.get("graph_version")

    nodes = result["nodes"]
    edges = result["edges"]

    if not include_schemas:
        nodes = [n for n in nodes if n["type"] != "SchemaComponent"]
        edges = [e for e in edges if e["type"] != "REFERENCES"]

    if depth > 0:
        # Limit traversal depth (simplified: return all, frontend handles depth)
        pass

    warnings: list[str] = []
    # Check for orphan endpoints
    service_ids = {n["id"] for n in nodes if n["type"] == "Service"}
    endpoint_providers = set()
    for e in edges:
        if e["type"] == "PROVIDES":
            endpoint_providers.add(e["target"])
    for n in nodes:
        if n["type"] == "APIEndpoint" and n["id"] not in {e["target"] for e in edges if e["type"] == "PROVIDES"}:
            warnings.append(f"Orphan endpoint: {n['label']}")

    return {
        "spec_id": None,
        "version_id": version_id,
        "graph_version": graph_version,
        "stale": False,
        "nodes": nodes,
        "edges": edges,
        "warnings": warnings,
    }
