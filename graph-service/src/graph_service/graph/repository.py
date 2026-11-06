from __future__ import annotations

import json
import logging
from contextlib import contextmanager
from pathlib import Path
from typing import Any

import kuzu

logger = logging.getLogger(__name__)


class GraphRepository:
    """The only module that calls kuzu.Connection.execute."""

    def __init__(self, db_path: str) -> None:
        self._db_path = db_path
        self._db: kuzu.Database | None = None
        self._conn: kuzu.Connection | None = None

    def open(self) -> None:
        path = Path(self._db_path)
        path.mkdir(parents=True, exist_ok=True)
        db_path = str(path / "blastspec.kuzu")
        self._db = kuzu.Database(db_path)
        self._conn = kuzu.Connection(self._db)
        from .schema import bootstrap_schema

        bootstrap_schema(self._db)
        logger.info("Kuzu opened at %s", self._db_path)

    def close(self) -> None:
        if self._conn is not None:
            self._conn.close()
            self._conn = None
        if self._db is not None:
            self._db.close()
            self._db = None

    @property
    def is_open(self) -> bool:
        return self._conn is not None

    @contextmanager
    def connection(self):
        if self._conn is None:
            raise RuntimeError("Kuzu connection is not open")
        yield self._conn

    def execute(self, query: str, params: dict[str, Any] | None = None) -> list[dict[str, Any]]:
        if self._conn is None:
            raise RuntimeError("Kuzu connection is not open")
        result = self._conn.execute(query, params or {})
        rows: list[dict[str, Any]] = []
        while result.has_next():
            rows.append(result.get_next())
        return rows

    def clear_workspace(self, workspace_id: str) -> None:
        """Delete all nodes and edges for a workspace (used before sync)."""
        with self.connection():
            self.execute(
                "MATCH (n) WHERE n.workspace_id = $ws DETACH DELETE n",
                {"ws": workspace_id},
            )

    def upsert_service(self, node: dict[str, Any]) -> None:
        self.execute(
            "MERGE (s:Service {id: $id}) "
            "SET s.workspace_id = $ws, s.name = $name, s.slug = $slug, s.owner_team = $owner",
            {
                "id": node["id"],
                "ws": node["workspace_id"],
                "name": node["name"],
                "slug": node["slug"],
                "owner": node["owner_team"],
            },
        )

    def upsert_endpoint(self, node: dict[str, Any]) -> None:
        self.execute(
            "MERGE (e:APIEndpoint {id: $id}) "
            "SET e.workspace_id = $ws, e.key = $key, e.method = $method, "
            "e.path = $path, e.operation_id = $op, e.summary = $summary, "
            "e.spec_version_id = $sv",
            {
                "id": node["id"],
                "ws": node["workspace_id"],
                "key": node["key"],
                "method": node["method"],
                "path": node["path"],
                "op": node.get("operation_id") or "",
                "summary": node.get("summary") or "",
                "sv": node["spec_version_id"],
            },
        )

    def upsert_schema_component(self, node: dict[str, Any]) -> None:
        self.execute(
            "MERGE (c:SchemaComponent {id: $id}) "
            "SET c.workspace_id = $ws, c.key = $key, c.type = $type, "
            "c.format = $fmt, c.required = $req, c.enum_values = $enum",
            {
                "id": node["id"],
                "ws": node["workspace_id"],
                "key": node["key"],
                "type": node.get("type") or "",
                "fmt": node.get("format") or "",
                "req": bool(node.get("required", False)),
                "enum": json.dumps(node.get("enum_values")) if node.get("enum_values") else "",
            },
        )

    def upsert_consumer(self, node: dict[str, Any]) -> None:
        self.execute(
            "MERGE (c:Consumer {id: $id}) "
            "SET c.workspace_id = $ws, c.consumer_service_id = $cs, "
            "c.source = $src, c.confidence = $conf",
            {
                "id": node["id"],
                "ws": node["workspace_id"],
                "cs": node["consumer_service_id"],
                "src": node["source"],
                "conf": float(node["confidence"]),
            },
        )

    def create_provides(self, service_id: str, endpoint_id: str, spec_version_id: str) -> None:
        self.execute(
            "MATCH (s:Service {id: $sid}), (e:APIEndpoint {id: $eid}) "
            "MERGE (s)-[:PROVIDES {spec_version_id: $sv}]->(e)",
            {"sid": service_id, "eid": endpoint_id, "sv": spec_version_id},
        )

    def create_consumes(self, consumer_id: str, endpoint_id: str, source: str, confidence: float) -> None:
        self.execute(
            "MATCH (c:Consumer {id: $cid}), (e:APIEndpoint {id: $eid}) "
            "MERGE (c)-[:CONSUMES {source: $src, confidence: $conf}]->(e)",
            {"cid": consumer_id, "eid": endpoint_id, "src": source, "conf": confidence},
        )

    def create_depends_on(self, from_id: str, to_id: str, source: str, confidence: float) -> None:
        self.execute(
            "MATCH (a:Service {id: $from}), (b:Service {id: $to}) "
            "MERGE (a)-[:DEPENDS_ON {source: $src, confidence: $conf}]->(b)",
            {"from": from_id, "to": to_id, "src": source, "conf": confidence},
        )

    def create_references(self, endpoint_id: str, schema_id: str) -> None:
        self.execute(
            "MATCH (e:APIEndpoint {id: $eid}), (s:SchemaComponent {id: $sid}) "
            "MERGE (e)-[:REFERENCES {direction: 'out'}]->(s)",
            {"eid": endpoint_id, "sid": schema_id},
        )

    def upsert_graph_meta(self, workspace_id: str, graph_version: str, built_at: str) -> None:
        meta_id = f"graph-meta-{workspace_id}"
        self.execute(
            "MERGE (m:GraphMeta {id: $id}) "
            "SET m.workspace_id = $ws, m.graph_version = $gv, m.built_at = $ba",
            {"id": meta_id, "ws": workspace_id, "gv": graph_version, "ba": built_at},
        )

    def get_workspace_graph(self, workspace_id: str) -> dict[str, Any]:
        nodes: list[dict[str, Any]] = []
        edges: list[dict[str, Any]] = []

        for row in self.execute(
            "MATCH (s:Service {workspace_id: $ws}) RETURN s.id, s.name, s.slug, s.owner_team",
            {"ws": workspace_id},
        ):
            nodes.append({"id": row["s.id"], "type": "Service", "label": row["s.name"],
                          "data": {"slug": row["s.slug"], "owner_team": row["s.owner_team"]}})

        for row in self.execute(
            "MATCH (e:APIEndpoint {workspace_id: $ws}) RETURN e.id, e.key, e.method, e.path, e.operation_id",
            {"ws": workspace_id},
        ):
            nodes.append({"id": row["e.id"], "type": "APIEndpoint", "label": row["e.key"],
                          "data": {"method": row["e.method"], "path": row["e.path"]}})

        for row in self.execute(
            "MATCH (c:SchemaComponent {workspace_id: $ws}) RETURN c.id, c.key, c.type, c.format",
            {"ws": workspace_id},
        ):
            nodes.append({"id": row["c.id"], "type": "SchemaComponent", "label": row["c.key"],
                          "data": {"type": row["c.type"], "format": row["c.format"]}})

        for row in self.execute(
            "MATCH (c:Consumer {workspace_id: $ws}) RETURN c.id, c.consumer_service_id, c.source, c.confidence",
            {"ws": workspace_id},
        ):
            nodes.append({"id": row["c.id"], "type": "Consumer", "label": row["c.consumer_service_id"],
                          "data": {"source": row["c.source"], "confidence": row["c.confidence"]}})

        # Edges
        for row in self.execute(
            "MATCH (s:Service)-[r:PROVIDES]->(e:APIEndpoint) WHERE s.workspace_id = $ws RETURN s.id, e.id, r.spec_version_id",
            {"ws": workspace_id},
        ):
            edges.append({"id": f"provides-{row['s.id']}-{row['e.id']}", "source": row["s.id"],
                          "target": row["e.id"], "type": "PROVIDES",
                          "data": {"spec_version_id": row["r.spec_version_id"]}})

        for row in self.execute(
            "MATCH (c:Consumer)-[r:CONSUMES]->(e:APIEndpoint) WHERE c.workspace_id = $ws RETURN c.id, e.id, r.source, r.confidence",
            {"ws": workspace_id},
        ):
            edges.append({"id": f"consumes-{row['c.id']}-{row['e.id']}", "source": row["c.id"],
                          "target": row["e.id"], "type": "CONSUMES",
                          "data": {"source": row["r.source"], "confidence": row["r.confidence"]}})

        for row in self.execute(
            "MATCH (a:Service)-[r:DEPENDS_ON]->(b:Service) WHERE a.workspace_id = $ws RETURN a.id, b.id, r.source, r.confidence",
            {"ws": workspace_id},
        ):
            edges.append({"id": f"depends-{row['a.id']}-{row['b.id']}", "source": row["a.id"],
                          "target": row["b.id"], "type": "DEPENDS_ON",
                          "data": {"source": row["r.source"], "confidence": row["r.confidence"]}})

        for row in self.execute(
            "MATCH (e:APIEndpoint)-[r:REFERENCES]->(s:SchemaComponent) WHERE e.workspace_id = $ws RETURN e.id, s.id",
            {"ws": workspace_id},
        ):
            edges.append({"id": f"refs-{row['e.id']}-{row['s.id']}", "source": row["e.id"],
                          "target": row["s.id"], "type": "REFERENCES", "data": {}})

        # Graph version
        gv_rows = self.execute(
            "MATCH (m:GraphMeta {workspace_id: $ws}) RETURN m.graph_version",
            {"ws": workspace_id},
        )
        graph_version = gv_rows[0]["m.graph_version"] if gv_rows else None

        return {"nodes": nodes, "edges": edges, "graph_version": graph_version}
