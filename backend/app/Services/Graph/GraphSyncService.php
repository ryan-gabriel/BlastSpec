<?php

namespace BlastSpec\Services\Graph;

use Illuminate\Support\Facades\Http;

final class GraphSyncService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 10,
    ) {}

    /** @param array<string, mixed> $snapshot */
    public function sync(array $snapshot): array
    {
        $response = Http::withHeaders([
            'X-Internal-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/internal/graph/sync', $snapshot);

        if ($response->status() === 409) {
            throw new \RuntimeException('Snapshot hash mismatch.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Graph sync failed: ' . $response->body());
        }

        return $response->json();
    }
}
