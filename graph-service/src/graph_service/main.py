from __future__ import annotations

from contextlib import asynccontextmanager

from fastapi import FastAPI

from .core.config import Settings
from .graph.repository import GraphRepository
from .api.impact import router as impact_router
from .api.migration import router as migration_router
from .api.query import router as query_router
from .api.sync import router as sync_router

settings = Settings()
repository = GraphRepository(settings.kuzu_path)


@asynccontextmanager
async def lifespan(app: FastAPI):
    repository.open()
    app.state.repository = repository
    yield
    repository.close()


app = FastAPI(title="BlastSpec Graph Service", lifespan=lifespan)
app.include_router(sync_router)
app.include_router(query_router)
app.include_router(impact_router)
app.include_router(migration_router)


@app.get("/internal/health")
async def health() -> dict:
    return {
        "status": "ok" if repository.is_open else "down",
        "kuzu": {"open": repository.is_open, "path_writable": True},
    }
