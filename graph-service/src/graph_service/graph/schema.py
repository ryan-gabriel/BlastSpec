from __future__ import annotations

import kuzu
from pathlib import Path

SCHEMA_DDL = [
    """CREATE NODE TABLE IF NOT EXISTS Service(
        id STRING,
        workspace_id STRING,
        name STRING,
        slug STRING,
        owner_team STRING,
        PRIMARY KEY (id)
    )""",
    """CREATE NODE TABLE IF NOT EXISTS APIEndpoint(
        id STRING,
        workspace_id STRING,
        key STRING,
        method STRING,
        path STRING,
        operation_id STRING,
        summary STRING,
        spec_version_id STRING,
        PRIMARY KEY (id)
    )""",
    """CREATE NODE TABLE IF NOT EXISTS SchemaComponent(
        id STRING,
        workspace_id STRING,
        key STRING,
        type STRING,
        format STRING,
        required BOOLEAN,
        enum_values STRING,
        PRIMARY KEY (id)
    )""",
    """CREATE NODE TABLE IF NOT EXISTS Consumer(
        id STRING,
        workspace_id STRING,
        consumer_service_id STRING,
        source STRING,
        confidence DOUBLE,
        PRIMARY KEY (id)
    )""",
    """CREATE NODE TABLE IF NOT EXISTS GraphMeta(
        id STRING,
        workspace_id STRING,
        graph_version STRING,
        built_at STRING,
        PRIMARY KEY (id)
    )""",
    """CREATE REL TABLE IF NOT EXISTS PROVIDES(
        FROM Service TO APIEndpoint,
        spec_version_id STRING
    )""",
    """CREATE REL TABLE IF NOT EXISTS CONSUMES(
        FROM Consumer TO APIEndpoint,
        source STRING,
        confidence DOUBLE
    )""",
    """CREATE REL TABLE IF NOT EXISTS DEPENDS_ON(
        FROM Service TO Service,
        source STRING,
        confidence DOUBLE
    )""",
    """CREATE REL TABLE IF NOT EXISTS REFERENCES(
        FROM APIEndpoint TO SchemaComponent,
        direction STRING
    )""",
]


def bootstrap_schema(db: kuzu.Database) -> None:
    """Create all node and relationship tables if they do not exist."""
    conn = kuzu.Connection(db)
    for ddl in SCHEMA_DDL:
        conn.execute(ddl)
    conn.close()
