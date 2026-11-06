<?php

namespace BlastSpec\Support;

/**
 * Validates required runtime configuration at boot.
 *
 * Rules mirror docs/ENVIRONMENT.md (boot validation section).
 */
final class ConfigValidator
{
    /** @var array<string, array{0: string, 1: bool}> */
    private const REQUIRED = [
        'APP_NAME' => ['BlastSpec', false],
        'APP_ENV' => ['local', true],
        'DB_CONNECTION' => ['pgsql', false],
        'DB_HOST' => ['postgres', false],
        'DB_PORT' => ['5432', false],
        'DB_DATABASE' => ['blastspec', false],
        'DB_USERNAME' => ['blastspec', false],
        'GRAPH_SERVICE_URL' => ['http://localhost:8000', false],
        'GRAPH_TIMEOUT_SECONDS' => ['10', false],
        'WEBHOOK_TOLERANCE_SECONDS' => ['300', false],
        'SESSION_DRIVER' => ['redis', false],
        'SESSION_LIFETIME' => ['480', false],
        'CORS_ALLOWED_ORIGINS' => ['http://localhost:3000', false],
        'RATE_LIMIT_API_PER_MINUTE' => ['120', false],
        'RATE_LIMIT_LOGIN_PER_MINUTE' => ['5', false],
        'RATE_LIMIT_UPLOAD_PER_MINUTE' => ['10', false],
        'RATE_LIMIT_WEBHOOK_PER_MINUTE' => ['60', false],
        'OUTBOX_POLL_INTERVAL_MS' => ['1000', false],
        'OUTBOX_BATCH_SIZE' => ['50', false],
        'OUTBOX_MAX_ATTEMPTS' => ['10', false],
        'STREAM_PENDING_TIMEOUT_SECONDS' => ['60', false],
        'STREAM_MAX_ATTEMPTS' => ['3', false],
        'STREAM_BACKOFF_SECONDS' => ['2,10,30', false],
        'NEXT_PUBLIC_API_URL' => ['http://localhost:8000/api/v1', false],
    ];

    /** @return array<int, string> */
    public function validate(): array
    {
        $errors = [];

        foreach (self::REQUIRED as $key => [$default]) {
            $value = (string) config($key, $default);
            if (trim($value) === '') {
                $errors[] = sprintf('Missing required configuration key [%s].', $key);
            }
        }

        if ((string) config('APP_KEY', '') === '') {
            $errors[] = 'Missing required configuration key [APP_KEY].';
        }

        if ((string) config('DB_PASSWORD', '') === '') {
            $errors[] = 'Missing required configuration key [DB_PASSWORD].';
        }

        if ((string) config('GRAPH_SERVICE_TOKEN', '') === '') {
            $errors[] = 'Missing required configuration key [GRAPH_SERVICE_TOKEN].';
        }

        if ((string) config('WEBHOOK_SECRET', '') === '') {
            $errors[] = 'Missing required configuration key [WEBHOOK_SECRET].';
        }

        if ((string) config('APP_DEBUG', 'false') === 'true' && (string) config('APP_ENV', 'local') === 'production') {
            $errors[] = '[APP_DEBUG] must be false when APP_ENV is production.';
        }

        if ((int) config('GRAPH_TIMEOUT_SECONDS', 10) <= 0) {
            $errors[] = '[GRAPH_TIMEOUT_SECONDS] must be a positive integer.';
        }

        if ((int) config('WEBHOOK_TOLERANCE_SECONDS', 300) <= 0) {
            $errors[] = '[WEBHOOK_TOLERANCE_SECONDS] must be a positive integer.';
        }

        if ((int) config('SESSION_LIFETIME', 480) <= 0) {
            $errors[] = '[SESSION_LIFETIME] must be a positive integer.';
        }

        if ((int) config('RATE_LIMIT_API_PER_MINUTE', 120) <= 0
            || (int) config('RATE_LIMIT_LOGIN_PER_MINUTE', 5) <= 0
            || (int) config('RATE_LIMIT_UPLOAD_PER_MINUTE', 10) <= 0
            || (int) config('RATE_LIMIT_WEBHOOK_PER_MINUTE', 60) <= 0) {
            $errors[] = 'Rate limit configuration values must be positive integers.';
        }

        if (filter_var((string) config('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'), FILTER_VALIDATE_URL) === false) {
            $errors[] = '[CORS_ALLOWED_ORIGINS] must be a valid URL.';
        }

        if (in_array('*', explode(',', (string) config('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')), [true], true)
            && (string) config('SESSION_SECURE_COOKIE', 'false') === 'true') {
            $errors[] = '[CORS_ALLOWED_ORIGINS] cannot contain a wildcard when credentials are enabled.';
        }

        return $errors;
    }
}
