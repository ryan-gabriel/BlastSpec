<?php

namespace BlastSpec\Services\Graph;

use BlastSpec\Models\ConsumerBinding;
use BlastSpec\Models\Service;
use BlastSpec\Models\SpecVersion;
use Illuminate\Support\Str;

final class ConsumerDiscovery
{
    /**
     * Discover consumer bindings for a spec version and persist them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discover(string $workspaceId, SpecVersion $version): array
    {
        $bindings = [];

        // 1. Declared: x-consumers in the spec
        $normalized = $version->normalized ?? [];
        $consumers = $normalized['consumers'] ?? [];

        // Also check root-level x-consumers
        // (already extracted during canonicalization into 'consumers' key)

        foreach ($consumers as $consumer) {
            if (! is_array($consumer)) {
                continue;
            }
            $serviceName = (string) ($consumer['service'] ?? '');
            if ($serviceName === '') {
                continue;
            }

            $consumerService = Service::query()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $serviceName)
                ->first();

            if ($consumerService === null) {
                continue;
            }

            $method = isset($consumer['method']) ? strtoupper((string) $consumer['method']) : null;
            $path = $consumer['path'] ?? null;

            if ($method !== null && $path !== null) {
                $bindings[] = $this->createBinding(
                    $workspaceId,
                    $version->spec->service_id,
                    $consumerService->id,
                    $method,
                    (string) $path,
                    'declared',
                    (float) ($consumer['confidence'] ?? 1.0),
                    ['source' => 'x-consumers'],
                );
            } else {
                // String form: consumes all endpoints
                $endpoints = $normalized['endpoints'] ?? [];
                foreach ($endpoints as $ep) {
                    $bindings[] = $this->createBinding(
                        $workspaceId,
                        $version->spec->service_id,
                        $consumerService->id,
                        $ep['method'],
                        $ep['path'],
                        'declared',
                        1.0,
                        ['source' => 'x-consumers', 'scope' => 'all'],
                    );
                }
            }
        }

        // 2. Inferred: host/base-URL matching
        $servers = $normalized['servers'] ?? [];
        $providerService = $version->spec->service;

        foreach ($servers as $server) {
            $serverUrl = (string) ($server['url'] ?? '');
            if ($serverUrl === '') {
                continue;
            }

            $host = parse_url($serverUrl, PHP_URL_HOST);
            if ($host === false || $host === null) {
                continue;
            }

            // Find services whose base_url matches this host
            $candidateServices = Service::query()
                ->where('workspace_id', $workspaceId)
                ->where('id', '!=', $version->spec->service_id)
                ->whereNotNull('base_url')
                ->get();

            foreach ($candidateServices as $candidate) {
                $candidateHost = parse_url((string) $candidate->base_url, PHP_URL_HOST);
                if ($candidateHost === $host) {
                    $endpoints = $normalized['endpoints'] ?? [];
                    foreach ($endpoints as $ep) {
                        $bindings[] = $this->createBinding(
                            $workspaceId,
                            $version->spec->service_id,
                            $candidate->id,
                            $ep['method'],
                            $ep['path'],
                            'inferred',
                            0.6,
                            ['source' => 'host-match', 'host' => $host],
                        );
                    }
                }
            }
        }

        // 3. Manual bindings are already in the DB; they win on conflict.
        // Deduplicate: higher precedence wins
        $deduped = $this->deduplicate($bindings);

        // Persist
        foreach ($deduped as $binding) {
            ConsumerBinding::query()->firstOrCreate(
                [
                    'provider_service_id' => $binding['provider_service_id'],
                    'consumer_service_id' => $binding['consumer_service_id'],
                    'method' => $binding['method'],
                    'path' => $binding['path'],
                    'source' => $binding['source'],
                ],
                [
                    'id' => (string) Str::uuid7(),
                    'workspace_id' => $workspaceId,
                    'confidence' => $binding['confidence'],
                    'evidence' => $binding['evidence'],
                ],
            );
        }

        return $deduped;
    }

    /** @return array<string, mixed> */
    private function createBinding(
        string $workspaceId,
        string $providerServiceId,
        string $consumerServiceId,
        string $method,
        string $path,
        string $source,
        float $confidence,
        array $evidence,
    ): array
    {
        return [
            'workspace_id' => $workspaceId,
            'provider_service_id' => $providerServiceId,
            'consumer_service_id' => $consumerServiceId,
            'method' => strtoupper($method),
            'path' => $path,
            'source' => $source,
            'confidence' => $confidence,
            'evidence' => $evidence,
        ];
    }

    /** @param array<int, array<string, mixed>> $bindings */
    private function deduplicate(array $bindings): array
    {
        $precedence = ['manual' => 3, 'declared' => 2, 'inferred' => 1];
        $seen = [];

        foreach ($bindings as $binding) {
            $key = $binding['provider_service_id'] . '|' . $binding['consumer_service_id'] . '|' . $binding['method'] . '|' . $binding['path'];

            if (! isset($seen[$key]) || $precedence[$binding['source']] > $precedence[$seen[$key]['source']]) {
                $seen[$key] = $binding;
            }
        }

        return array_values($seen);
    }
}
