<?php

return [
    'graph_service_url' => env('GRAPH_SERVICE_URL', 'http://graph-service:8001'),
    'graph_service_token' => env('GRAPH_SERVICE_TOKEN', ''),
    'graph_timeout_seconds' => (int) env('GRAPH_TIMEOUT_SECONDS', 10),
    'webhook_secret' => env('WEBHOOK_SECRET', ''),
    'webhook_previous_secret' => env('WEBHOOK_PREVIOUS_SECRET', ''),
    'webhook_tolerance_seconds' => (int) env('WEBHOOK_TOLERANCE_SECONDS', 300),
    'outbox' => [
        'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 50),
        'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 10),
        'poll_interval_ms' => (int) env('OUTBOX_POLL_INTERVAL_MS', 1000),
    ],
    'stream' => [
        'max_attempts' => (int) env('STREAM_MAX_ATTEMPTS', 3),
        'pending_timeout_seconds' => (int) env('STREAM_PENDING_TIMEOUT_SECONDS', 60),
        'backoff' => array_map('intval', explode(',', env('STREAM_BACKOFF_SECONDS', '2,10,30'))),
    ],
    'session' => [
        'lifetime' => (int) env('SESSION_LIFETIME', 480),
        'secure_cookie' => (bool) env('SESSION_SECURE_COOKIE', false),
    ],
];
