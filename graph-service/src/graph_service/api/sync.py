from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from typing import Any

from fastapi import APIRouter, HTTPException, Request

from ..graph.repository import GraphRepository

router = APIRouter(prefix="/internal/graph", tags=["internal"])


def _get_repo(request: Request) -> GraphRepository:
    return request.app.state.repository


@router.post("/sync")
async def sync(request: Request) -> dict[str, Any]:
    repo = _get_repo(request)
    body = await request.json()

    workspace_id = body["workspace_id"]
    snapshot_hash = body["snapshot_hash"]

    # Recompute hash
    canonical = {
        "workspace_id": workspace_id,
        "services": sorted(body.get("services", []), key=lambda s: s["id"]),
        "endpoints": sorted(body.get("endpoints", []), key=lambda e: e["key"]),
        "schema_components": sorted(body.get("schema_components", []), key=lambda c: c["key"]),
        "consumer_bindings": sorted(
            body.get("consumer_bindings", []),
            key=lambda b: (b["provider_service_id"], b["method"], b["path"], b["consumer_service_id"], b["source"]),
        ),
    }
    computed = hashlib.sha256(json.dumps(canonical, sort_keys=True).encode()).hexdigest()

    if computed != snapshot_hash:
        raise HTTPException(status_code=409, detail="Snapshot hash mismatch")

    # Clear and rebuild
    repo.clear_workspace(workspace_id)

    for svc in body.get("services", []):
        repo.upsert_service({
            "id": svc["id"],
            "workspace_id": workspace_id,
            "name": svc["name"],
            "slug": svc.get("slug", svc["name"]),
            "owner_team": svc["owner_team"],
        })

    for ep in body.get("endpoints", []):
        repo.upsert_endpoint({
            "id": ep["id"],
            "workspace_id": workspace_id,
            "key": ep["key"],
            "method": ep["method"],
            "path": ep["path"],
            "operation_id": ep.get("operation_id"),
            "summary": ep.get("summary"),
            "spec_version_id": ep["spec_version_id"],
        })
        repo.create_provides(ep["provider_service_id"], ep["id"], ep["spec_version_id"])

    for sc in body.get("schema_components", []):
        repo.upsert_schema_component({
            "id": sc["id"],
            "workspace_id": workspace_id,
            "key": sc["key"],
            "type": sc.get("type"),
            "format": sc.get("format"),
            "required": sc.get("required", False),
            "enum_values": sc.get("enum_values"),
        })
        if sc.get("endpoint_id"):
            repo.create_references(sc["endpoint_id"], sc["id"])

    for cb in body.get("consumer_bindings", []):
        # Create consumer node
        import uuid as _uuid

        consumer_id = str(_uuid.uuid5(_uuid.NAMESPACE_URL, f"{cb['consumer_service_id']}:{cb['provider_service_id']}:{cb['method']} {cb['path']}"))
        repo.upsert_consumer({
            "id": consumer_id,
            "workspace_id": workspace_id,
            "consumer_service_id": cb["consumer_service_id"],
            "source": cb["source"],
            "confidence": cb["confidence"],
        })
        # Find endpoint id for this method+path+provider
        # (simplified: store binding info for later resolution)
        repo.create_consumes(consumer_id, cb.get("endpoint_id", ""), cb["source"], cb["confidence"])

    # Create DEPENDS_ON edges from consumer bindings
    seen_deps: set[tuple[str, str]] = set()
    for cb in body.get("consumer_bindings", []):
        key = (cb["consumer_service_id"], cb["provider_service_id"])
        if key not in seen_deps:
            seen_deps.add(key)
            repo.create_depends_on(cb["consumer_service_id"], cb["provider_service_id"], cb["source"], cb["confidence"])

    built_at = datetime.now(timezone.utc).isoformat()
    repo.upsert_graph_meta(workspace_id, snapshot_hash, built_at)

    node_count = len(body.get("services", [])) + len(body.get("endpoints", [])) + len(body.get("schema_components", [])) + len(body.get("consumer_bindings", []))
    edge_count = len(body.get("endpoints", [])) + len(body.get("consumer_bindings", [])) + len(seen_deps) + len(body.get("schema_components", []))

    return {
        "graph_version": snapshot_hash,
        "nodes": node_count,
        "edges": edge_count,
        "duration_ms": 0,
    }
