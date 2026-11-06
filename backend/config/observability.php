<?php

// Observability configuration
// See DEPLOYMENT.md §9 for metrics and alerts

// trace_id propagation: X-Request-Id header → request attributes → logs
// Structured JSON logging in production
// Metrics: outbox pending count, stream lag, DLQ size, graph sync duration, diff duration, impact duration
// Alerts: outbox pending > 100 for 5min, DLQ > 0, graph sync failure > 5%, API 5xx > 1%, worker heartbeat missing 2min
