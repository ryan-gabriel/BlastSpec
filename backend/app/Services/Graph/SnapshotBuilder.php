<?php

namespace BlastSpec\Services\Graph;

use BlastSpec\Models\ConsumerBinding;
use BlastSpec\Models\Service;
use BlastSpec\Models\SpecVersion;

final class SnapshotBuilder
{
    /** @return array<string, mixed> */
    public function build(string $workspaceId): array
    {
        $services = Service::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $serviceMap = [];
        $serviceNodes = [];
        foreach ($services as $service) {
            $serviceNodes[] = [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'owner_team' => $service->owner_team,
            ];
            $serviceMap[$service->id] = $service;
        }

        // Get all parsed spec versions for this workspace
        $versions = SpecVersion::query()
            ->where('workspace_id', $workspaceId)
            ->where('parse_status', 'parsed')
            ->orderBy('id')
            ->get();

        $endpointNodes = [];
        $schemaNodes = [];
        $bindings = [];

        foreach ($versions as $version) {
            $normalized = $version->normalized ?? [];
            $endpoints = $normalized['endpoints'] ?? [];
            $components = $normalized['components']['schemas'] ?? [];

            foreach ($endpoints as $ep) {
                $endpointId = $this->uuid5($workspaceId . ':' . $version->spec->service_id . ':' . $ep['key']);

                $endpointNodes[] = [
                    'id' => $endpointId,
                    'provider_service_id' => $version->spec->service_id,
                    'spec_version_id' => $version->id,
                    'key' => $ep['key'],
                    'method' => $ep['method'],
                    'path' => $ep['path'],
                    'operation_id' => $ep['operation_id'] ?? null,
                    'summary' => $ep['summary'] ?? null,
                ];
            }

            foreach ($components as $compName => $compSchema) {
                $schemaId = $this->uuid5($version->spec->service_id . ':' . $compName);
                $schemaNodes[] = [
                    'id' => $schemaId,
                    'provider_service_id' => $version->spec->service_id,
                    'key' => $compName,
                    'type' => $compSchema['type'] ?? 'object',
                    'format' => $compSchema['format'] ?? null,
                    'required' => $compSchema['required'] ?? [],
                    'enum_values' => $compSchema['enum'] ?? null,
                    'endpoint_id' => null,
                ];
            }
        }

        // Consumer bindings
        $consumerBindings = ConsumerBinding::query()
            ->where('workspace_id', $workspaceId)
            ->get();

        foreach ($consumerBindings as $cb) {
            // Find endpoint id for this binding
            $endpointId = null;
            foreach ($endpointNodes as $ep) {
                if ($ep['provider_service_id'] === $cb->provider_service_id
                    && $ep['method'] === $cb->method
                    && $ep['path'] === $cb->path) {
                    $endpointId = $ep['id'];
                    break;
                }
            }

            $bindings[] = [
                'provider_service_id' => $cb->provider_service_id,
                'consumer_service_id' => $cb->consumer_service_id,
                'method' => $cb->method,
                'path' => $cb->path,
                'source' => $cb->source,
                'confidence' => (float) $cb->confidence,
                'endpoint_id' => $endpointId,
            ];
        }

        // Compute snapshot hash
        $canonical = [
            'workspace_id' => $workspaceId,
            'services' => array_values(array_map(fn ($s) => ['id' => $s['id']], $serviceNodes)),
            'endpoints' => array_values(array_map(fn ($e) => ['key' => $e['key']], $endpointNodes)),
            'schema_components' => array_values(array_map(fn ($c) => ['key' => $c['key']], $schemaNodes)),
            'consumer_bindings' => array_values(array_map(fn ($b) => [
                'provider_service_id' => $b['provider_service_id'],
                'method' => $b['method'],
                'path' => $b['path'],
                'consumer_service_id' => $b['consumer_service_id'],
                'source' => $b['source'],
            ], $bindings)),
        ];

        $snapshotHash = hash('sha256', json_encode($canonical, JSON_SORT_KEYS | JSON_UNESCAPED_SLASHES));

        return [
            'workspace_id' => $workspaceId,
            'snapshot_hash' => $snapshotHash,
            'services' => $serviceNodes,
            'endpoints' => $endpointNodes,
            'schema_components' => $schemaNodes,
            'consumer_bindings' => $bindings,
        ];
    }

    private function uuid5(string $name): string
    {
        return \Illuminate\Support\Str::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $name)->toString();
    }
}
