<?php

namespace BlastSpec\Services\Spec;

final class Canonicalizer
{
    /** @param array<string, mixed> $document */
    public function canonicalize(array $document): array
    {
        $normalized = [
            'schema_version' => 1,
            'openapi' => (string) ($document['openapi'] ?? ''),
            'info' => $this->canonicalizeInfo($document['info'] ?? []),
            'servers' => $this->canonicalizeServers($document['servers'] ?? []),
            'consumers' => $this->canonicalizeConsumers($document),
            'endpoints' => $this->canonicalizeEndpoints($document),
            'components' => $this->canonicalizeComponents($document['components'] ?? []),
            'security_schemes' => $this->canonicalizeSecuritySchemes($document['components']['securitySchemes'] ?? $document['components']['security_schemes'] ?? []),
        ];

        // Sort object keys recursively
        return $this->sortKeys($normalized);
    }

    /** @param mixed $value */
    private function canonicalizeInfo(mixed $value): array
    {
        if (! is_array($value)) {
            return ['title' => '', 'version' => ''];
        }

        return [
            'title' => (string) ($value['title'] ?? ''),
            'version' => (string) ($value['version'] ?? ''),
            'description' => isset($value['description']) ? (string) $value['description'] : null,
        ];
    }

    /** @param mixed $value */
    private function canonicalizeServers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $servers = [];
        foreach ($value as $server) {
            if (! is_array($server)) {
                continue;
            }
            $servers[] = ['url' => (string) ($server['url'] ?? '')];
        }

        return $servers;
    }

    /** @param array<string, mixed> $document */
    private function canonicalizeConsumers(array $document): array
    {
        $consumers = [];

        // x-consumers at root
        if (isset($document['x-consumers'])) {
            $xc = $document['x-consumers'];
            if (is_array($xc)) {
                foreach ($xc as $entry) {
                    if (is_string($entry)) {
                        $consumers[] = ['service' => $entry, 'source' => 'x-consumers'];
                    } elseif (is_array($entry)) {
                        $consumers[] = [
                            'service' => (string) ($entry['service'] ?? ''),
                            'method' => isset($entry['method']) ? strtoupper((string) $entry['method']) : null,
                            'path' => $entry['path'] ?? null,
                            'confidence' => isset($entry['confidence']) ? (float) $entry['confidence'] : 1.0,
                            'source' => 'x-consumers',
                        ];
                    }
                }
            }
        }

        return $consumers;
    }

    /** @param array<string, mixed> $document */
    private function canonicalizeEndpoints(array $document): array
    {
        $paths = $document['paths'] ?? [];
        if (! is_array($paths)) {
            return [];
        }

        $endpoints = [];
        $methods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

        foreach ($paths as $path => $operations) {
            if (! is_array($operations)) {
                continue;
            }

            $normalizedPath = $this->normalizePath((string) $path);

            foreach ($operations as $method => $operation) {
                $methodLower = strtolower((string) $method);
                if (! in_array($methodLower, $methods, true)) {
                    continue;
                }
                if (! is_array($operation)) {
                    continue;
                }

                $endpoint = [
                    'key' => strtoupper($methodLower) . ' ' . $normalizedPath,
                    'method' => strtoupper($methodLower),
                    'path' => $normalizedPath,
                    'operation_id' => isset($operation['operationId']) ? (string) $operation['operationId'] : null,
                    'summary' => isset($operation['summary']) ? (string) $operation['summary'] : null,
                    'description' => isset($operation['description']) ? (string) $operation['description'] : null,
                    'tags' => $this->sortStrings($operation['tags'] ?? []),
                    'deprecated' => (bool) ($operation['deprecated'] ?? false),
                    'parameters' => $this->canonicalizeParameters($operation['parameters'] ?? []),
                    'request_body' => $this->canonicalizeRequestBody($operation['requestBody'] ?? null),
                    'responses' => $this->canonicalizeResponses($operation['responses'] ?? []),
                    'security' => $this->canonicalizeSecurity($operation['security'] ?? null),
                ];

                // x-consumers on operation
                if (isset($operation['x-consumers'])) {
                    // Already handled at document level for simplicity
                }

                $endpoints[] = $endpoint;
            }
        }

        // Sort endpoints by key
        usort($endpoints, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return $endpoints;
    }

    private function normalizePath(string $path): string
    {
        // Ensure leading slash
        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        // Collapse double slashes
        $path = preg_replace('#/{2,}#', '/', $path);
        // Trim trailing slash (except root)
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /** @param mixed $value */
    private function canonicalizeParameters(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $params = [];
        foreach ($value as $param) {
            if (! is_array($param)) {
                continue;
            }

            $paramOut = [
                'name' => (string) ($param['name'] ?? ''),
                'in' => (string) ($param['in'] ?? 'query'),
                'required' => (bool) ($param['required'] ?? false),
                'description' => isset($param['description']) ? (string) $param['description'] : null,
                'schema' => $this->canonicalizeSchema($param['schema'] ?? null),
            ];

            $params[] = $paramOut;
        }

        // Sort by (in, name)
        usort($params, fn ($a, $b) => strcmp($a['in'] . '|' . $a['name'], $b['in'] . '|' . $b['name']));

        return $params;
    }

    /** @param mixed $value */
    private function canonicalizeRequestBody(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $content = [];
        if (isset($value['content']) && is_array($value['content'])) {
            foreach ($value['content'] as $mediaType => $mediaObj) {
                $mediaType = strtolower(trim((string) $mediaType));
                // Strip parameters
                if (str_contains($mediaType, ';')) {
                    $mediaType = trim(explode(';', $mediaType)[0]);
                }
                $content[$mediaType] = [
                    'schema' => $this->canonicalizeSchema($mediaObj['schema'] ?? null),
                    'schema_ref' => $this->extractRef($mediaObj['schema'] ?? null),
                ];
            }
            ksort($content);
        }

        return [
            'required' => (bool) ($value['required'] ?? false),
            'description' => isset($value['description']) ? (string) $value['description'] : null,
            'content' => $content,
        ];
    }

    /** @param mixed $value */
    private function canonicalizeResponses(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $responses = [];
        foreach ($value as $statusCode => $response) {
            if (! is_array($response)) {
                continue;
            }

            $content = [];
            if (isset($response['content']) && is_array($response['content'])) {
                foreach ($response['content'] as $mediaType => $mediaObj) {
                    $mediaType = strtolower(trim((string) $mediaType));
                    if (str_contains($mediaType, ';')) {
                        $mediaType = trim(explode(';', $mediaType)[0]);
                    }
                    $content[$mediaType] = [
                        'schema' => $this->canonicalizeSchema($mediaObj['schema'] ?? null),
                        'schema_ref' => $this->extractRef($mediaObj['schema'] ?? null),
                    ];
                }
                ksort($content);
            }

            $headers = [];
            if (isset($response['headers']) && is_array($response['headers'])) {
                foreach ($response['headers'] as $headerName => $headerObj) {
                    if (! is_array($headerObj)) {
                        continue;
                    }
                    $headers[(string) $headerName] = [
                        'required' => (bool) ($headerObj['required'] ?? false),
                        'schema' => $this->canonicalizeSchema($headerObj['schema'] ?? null),
                    ];
                }
                ksort($headers);
            }

            $responses[(string) $statusCode] = [
                'description' => isset($response['description']) ? (string) $response['description'] : '',
                'headers' => $headers,
                'content' => $content,
            ];
        }

        // Sort keys numerically as strings
        ksort($responses, SORT_STRING);

        return $responses;
    }

    /** @param mixed $value */
    private function canonicalizeSchema(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        // Handle $ref
        if (isset($value['$ref']) && is_string($value['$ref'])) {
            return ['$ref' => $value['$ref']];
        }

        $schema = [];

        // Type
        if (isset($value['type'])) {
            if (is_array($value['type'])) {
                // OpenAPI 3.1 nullable union: type: [X, "null"]
                $types = $value['type'];
                $nullable = in_array('null', $types, true);
                $nonNullTypes = array_values(array_filter($types, fn ($t) => $t !== 'null'));
                $schema['type'] = count($nonNullTypes) === 1 ? $nonNullTypes[0] : $nonNullTypes;
                if ($nullable) {
                    $schema['nullable'] = true;
                }
            } else {
                $schema['type'] = $value['type'];
                if (isset($value['nullable']) && $value['nullable']) {
                    $schema['nullable'] = true;
                }
            }
        }

        // Format
        if (isset($value['format'])) {
            $schema['format'] = $value['format'];
        }

        // Required
        if (isset($value['required']) && is_array($value['required'])) {
            $schema['required'] = $this->sortStrings($value['required']);
        }

        // Properties
        if (isset($value['properties']) && is_array($value['properties'])) {
            $props = [];
            foreach ($value['properties'] as $propName => $propSchema) {
                $props[(string) $propName] = $this->canonicalizeSchema($propSchema);
            }
            ksort($props);
            $schema['properties'] = $props;
        }

        // Items (array)
        if (isset($value['items'])) {
            $schema['items'] = $this->canonicalizeSchema($value['items']);
        }

        // Enum
        if (isset($value['enum']) && is_array($value['enum'])) {
            $schema['enum'] = $this->sortMixed($value['enum']);
        }

        // Additional properties
        if (isset($value['additionalProperties'])) {
            if (is_bool($value['additionalProperties'])) {
                $schema['additionalProperties'] = $value['additionalProperties'];
            } elseif (is_array($value['additionalProperties'])) {
                $schema['additionalProperties'] = $this->canonicalizeSchema($value['additionalProperties']);
            }
        }

        // oneOf / anyOf / allOf
        foreach (['oneOf', 'anyOf', 'allOf'] as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                $schema[$key] = array_map(fn ($s) => $this->canonicalizeSchema($s), $value[$key]);
            }
        }

        // Constraints
        foreach (['minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems', 'default', 'example'] as $key) {
            if (array_key_exists($key, $value)) {
                $schema[$key] = $value[$key];
            }
        }

        // Nullable (OpenAPI 3.0 style)
        if (isset($value['nullable']) && $value['nullable'] && ! isset($schema['nullable'])) {
            $schema['nullable'] = true;
        }

        return $schema === [] ? null : $schema;
    }

    /** @param mixed $value */
    private function canonicalizeSecurity(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        $result = [];
        foreach ($value as $req) {
            if (! is_array($req)) {
                continue;
            }
            foreach ($req as $scheme => $scopes) {
                $result[] = [
                    'scheme' => (string) $scheme,
                    'scopes' => is_array($scopes) ? $this->sortStrings($scopes) : [],
                ];
            }
        }

        return $result;
    }

    /** @param mixed $value */
    private function canonicalizeSecuritySchemes(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $schemes = [];
        foreach ($value as $name => $scheme) {
            if (! is_array($scheme)) {
                continue;
            }
            $schemes[(string) $name] = [
                'type' => (string) ($scheme['type'] ?? ''),
                'scheme' => isset($scheme['scheme']) ? (string) $scheme['scheme'] : null,
                'bearerFormat' => isset($scheme['bearerFormat']) ? (string) $scheme['bearerFormat'] : null,
                'in' => isset($scheme['in']) ? (string) $scheme['in'] : null,
                'name' => isset($scheme['name']) ? (string) $scheme['name'] : null,
                'flows' => $scheme['flows'] ?? null,
                'openIdConnectUrl' => isset($scheme['openIdConnectUrl']) ? (string) $scheme['openIdConnectUrl'] : null,
            ];
        }

        ksort($schemes);

        return $schemes;
    }

    /** @param mixed $value */
    private function canonicalizeComponents(mixed $value): array
    {
        if (! is_array($value)) {
            return ['schemas' => []];
        }

        $schemas = [];
        if (isset($value['schemas']) && is_array($value['schemas'])) {
            foreach ($value['schemas'] as $name => $schema) {
                $schemas[(string) $name] = $this->canonicalizeSchema($schema);
            }
            ksort($schemas);
        }

        return ['schemas' => $schemas];
    }

    /** @param mixed $value */
    private function extractRef(mixed $value): ?string
    {
        if (is_array($value) && isset($value['$ref']) && is_string($value['$ref'])) {
            return $value['$ref'];
        }

        return null;
    }

    /** @param array<int, string> $values */
    private function sortStrings(array $values): array
    {
        $strings = array_map('strval', $values);
        sort($strings, SORT_STRING);

        return array_values($strings);
    }

    /** @param array<int, mixed> $values */
    private function sortMixed(array $values): array
    {
        // Separate scalars by type for stable sorting
        $sorted = $values;
        usort($sorted, function ($a, $b): int
        {
            $ta = gettype($a);
            $tb = gettype($b);
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }
            if (is_string($a) && is_string($b)) {
                return strcmp($a, $b);
            }
            if (is_numeric($a) && is_numeric($b)) {
                return $a <=> $b;
            }

            return strcmp((string) $a, (string) $b);
        });

        return array_values($sorted);
    }

    /** @param array<string, mixed> $data */
    private function sortKeys(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value) && array_is_list($value) === false) {
                $data[$key] = $this->sortKeys($value);
            } elseif (is_array($value)) {
                foreach ($value as $i => $item) {
                    if (is_array($item) && array_is_list($item) === false) {
                        $value[$i] = $this->sortKeys($item);
                    }
                }
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
