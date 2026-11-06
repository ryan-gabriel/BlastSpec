from __future__ import annotations

from pydantic import model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "BlastSpec"
    app_env: str = "local"
    debug: bool = False

    db_connection: str = "pgsql"
    db_host: str = "postgres"
    db_port: int = 5432
    db_name: str = "blastspec"
    db_user: str = "blastspec"
    db_password: str = ""

    redis_host: str = "redis"
    redis_port: int = 6379
    redis_password: str = ""
    redis_db: int = 0
    redis_cache_db: int = 1
    redis_prefix: str = "blastspec"
    stream_prefix: str = "blastspec"

    graph_service_url: str = "http://localhost:8000"
    graph_service_token: str = ""
    graph_timeout_seconds: int = 10
    kuzu_path: str = "/data/kuzu"
    graph_max_nodes: int = 100_000
    graph_max_edges: int = 500_000
    graph_node_return_limit: int = 50_000

    webhook_secret: str = ""
    webhook_previous_secret: str = ""
    webhook_tolerance_seconds: int = 300

    session_driver: str = "redis"
    session_lifetime: int = 480
    session_secure_cookie: bool = False

    demo_user_email: str = "demo@blastspec.dev"
    demo_user_password: str = "demo-password"
    demo_workspace_name: str = "Demo workspace"

    cors_allowed_origins: str = "http://localhost:3000"
    rate_limit_api_per_minute: int = 120
    rate_limit_login_per_minute: int = 5
    rate_limit_upload_per_minute: int = 10
    rate_limit_webhook_per_minute: int = 60

    outbox_poll_interval_ms: int = 1_000
    outbox_batch_size: int = 50
    outbox_max_attempts: int = 10
    stream_pending_timeout_seconds: int = 60
    stream_max_attempts: int = 3
    stream_backoff_seconds: str = "2,10,30"

    next_public_api_url: str = "http://localhost:8000/api/v1"
    next_public_app_name: str = "BlastSpec"

    sentry_dsn: str = ""
    metrics_enabled: bool = False

    @model_validator(mode="after")
    def _validate(self) -> "Settings":
        if len(self.graph_service_token) < 32:
            raise ValueError("GRAPH_SERVICE_TOKEN must be at least 32 characters long.")
        if self.app_env == "production" and len(self.webhook_secret) < 32:
            raise ValueError("WEBHOOK_SECRET must be at least 32 characters in production.")
        return self

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")
