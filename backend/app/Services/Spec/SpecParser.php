<?php

namespace BlastSpec\Services\Spec;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

final class SpecParser
{
    private int $nodeCount = 0;
    private int $refCount = 0;
    private int $aliasCount = 0;
    private float $startTime = 0.0;

    /** @return array{openapi_version: string|null, normalized: array<string, mixed>|null, errors: array<int, array{pointer: string, message: string}>, warnings: array<int, string>, endpoint_count: int} */
    public function parse(string $rawContent, string $extension = 'json'): array
    {
        $this->nodeCount = 0;
        $this->refCount = 0;
        $this->aliasCount = 0;
        $this->startTime = microtime(true);

        $result = [
            'openapi_version' => null,
            'normalized' => null,
            'errors' => [],
            'warnings' => [],
            'endpoint_count' => 0,
        ];

        // Size check (already done at controller, but defense in depth)
        if (strlen($rawContent) > SpecLimits::MAX_FILE_BYTES) {
            $result['errors'][] = ['pointer' => '/', 'message' => 'Spec exceeds 5 MB size limit.'];

            return $result;
        }

        $decoded = null;

        if (in_array($extension, ['yaml', 'yml'], true)) {
            try {
                $decoded = Yaml::parse($rawContent, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (ParseException $e) {
                $result['errors'][] = ['pointer' => '/', 'message' => 'YAML parse error: ' . $e->getMessage()];

                return $result;
            }
        } else {
            $decoded = json_decode($rawContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['errors'][] = ['pointer' => '/', 'message' => 'JSON parse error: ' . json_last_error_msg()];

                return $result;
            }
        }

        if (! is_array($decoded)) {
            $result['errors'][] = ['pointer' => '/', 'message' => 'Document must be a JSON object or YAML mapping.'];

            return $result;
        }

        // OpenAPI version check
        if (! isset($decoded['openapi']) || ! is_string($decoded['openapi'])) {
            $result['errors'][] = ['pointer' => '/openapi', 'message' => 'Missing or invalid "openapi" field.'];

            return $result;
        }

        $version = (string) $decoded['openapi'];
        if (! preg_match('/^3\.[01]\.\d+$/', $version)) {
            $result['errors'][] = ['pointer' => '/openapi', 'message' => sprintf('Unsupported OpenAPI version "%s". Use 3.0.x or 3.1.x.', $version)];

            return $result;
        }

        $majorMinor = substr($version, 0, 3);
        if (! in_array($majorMinor, SpecLimits::ALLOWED_OPENAPI_VERSIONS, true)) {
            $result['errors'][] = ['pointer' => '/openapi', 'message' => sprintf('Unsupported OpenAPI version "%s".', $version)];

            return $result;
        }

        // Depth check
        $depth = $this->maxDepth($decoded);
        if ($depth > SpecLimits::MAX_NESTING_DEPTH) {
            $result['errors'][] = ['pointer' => '/', 'message' => sprintf('Document nesting depth %d exceeds limit of %d.', $depth, SpecLimits::MAX_NESTING_DEPTH)];

            return $result;
        }

        // Node count check
        $this->countNodes($decoded);
        if ($this->nodeCount > SpecLimits::MAX_TOTAL_NODES) {
            $result['errors'][] = ['pointer' => '/', 'message' => sprintf('Document has %d nodes, exceeding limit of %d.', $this->nodeCount, SpecLimits::MAX_TOTAL_NODES)];

            return $result;
        }

        // Timeout check
        if ((microtime(true) - $this->startTime) > SpecLimits::MAX_PARSE_SECONDS) {
            $result['errors'][] = ['pointer' => '/', 'message' => 'Parse exceeded 5 second time limit.'];

            return $result;
        }

        // $ref resolution (local only)
        $resolved = $this->resolveRefs($decoded, $decoded, '#', []);
        if ($resolved === null) {
            // Errors already recorded? We'll add a generic one
            $result['errors'][] = ['pointer' => '/', 'message' => 'Failed to resolve $ref references.'];

            return $result;
        }

        // Endpoint count check
        $endpointCount = 0;
        if (isset($resolved['paths']) && is_array($resolved['paths'])) {
            foreach ($resolved['paths'] as $path => $operations) {
                if (is_array($operations)) {
                    foreach (array_keys($operations) as $method) {
                        if (in_array(strtolower((string) $method), ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'], true)) {
                            $endpointCount++;
                        }
                    }
                }
            }
        }

        if ($endpointCount > SpecLimits::MAX_ENDPOINTS) {
            $result['errors'][] = ['pointer' => '/paths', 'message' => sprintf('Document has %d endpoints, exceeding limit of %d.', $endpointCount, SpecLimits::MAX_ENDPOINTS)];

            return $result;
        }

        $result['openapi_version'] = $version;
        $result['normalized'] = $resolved;
        $result['endpoint_count'] = $endpointCount;

        return $result;
    }

    private function maxDepth(mixed $value, int $current = 0): int
    {
        if (! is_array($value) || $value === []) {
            return $current;
        }

        $max = $current;
        foreach ($value as $item) {
            $max = max($max, $this->maxDepth($item, $current + 1));
        }

        return $max;
    }

    private function countNodes(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $this->nodeCount += count($value);

        foreach ($value as $item) {
            $this->countNodes($item);
        }
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $root
     * @param string $pointer
     * @param array<int, string> $path
     * @return array<string, mixed>|null
     */
    private function resolveRefs(array $document, array $root, string $pointer, array $path): ?array
    {
        if ($this->refCount > SpecLimits::MAX_REF_COUNT) {
            return null;
        }

        if (count($path) > SpecLimits::MAX_REF_HOPS) {
            return null;
        }

        $result = [];

        foreach ($document as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $this->refCount++;

                if (! str_starts_with($value, '#/')) {
                    // Remote ref - reject
                    return null;
                }

                // Resolve local ref
                $segments = explode('/', ltrim($value, '#/'));
                $target = $root;
                foreach ($segments as $segment) {
                    $segment = urldecode($segment);
                    if (! is_array($target) || ! array_key_exists($segment, $target)) {
                        return null;
                    }
                    $target = $target[$segment];
                }

                if (! is_array($target)) {
                    return null;
                }

                // Prevent circular refs
                if (in_array($value, $path, true)) {
                    return null;
                }

                $resolved = $this->resolveRefs($target, $root, $value, array_merge($path, [$value]));

                if ($resolved === null) {
                    return null;
                }

                // Merge resolved into result (replace the $ref key with resolved content)
                foreach ($resolved as $rk => $rv) {
                    $result[$rk] = $rv;
                }

                continue;
            }

            if (is_array($value)) {
                $resolved = $this->resolveRefs($value, $root, $pointer . '/' . $key, $path);
                if ($resolved === null) {
                    return null;
                }
                $result[$key] = $resolved;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
