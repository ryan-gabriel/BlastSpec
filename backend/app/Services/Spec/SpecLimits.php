<?php

namespace BlastSpec\Services\Spec;

final class SpecLimits
{
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;
    public const MAX_PARSE_SECONDS = 5;
    public const MAX_NESTING_DEPTH = 64;
    public const MAX_TOTAL_NODES = 200_000;
    public const MAX_REF_HOPS = 32;
    public const MAX_REF_COUNT = 1_000;
    public const MAX_ENDPOINTS = 5_000;
    public const MAX_YAML_ALIASES = 100;
    public const ALLOWED_EXTENSIONS = ['json', 'yaml', 'yml'];
    public const ALLOWED_OPENAPI_VERSIONS = ['3.0', '3.1'];

    /** @return array<string, int|string|array> */
    public static function toArray(): array
    {
        return [
            'max_file_bytes' => self::MAX_FILE_BYTES,
            'max_parse_seconds' => self::MAX_PARSE_SECONDS,
            'max_nesting_depth' => self::MAX_NESTING_DEPTH,
            'max_total_nodes' => self::MAX_TOTAL_NODES,
            'max_ref_hops' => self::MAX_REF_HOPS,
            'max_ref_count' => self::MAX_REF_COUNT,
            'max_endpoints' => self::MAX_ENDPOINTS,
            'max_yaml_aliases' => self::MAX_YAML_ALIASES,
            'allowed_extensions' => self::ALLOWED_EXTENSIONS,
            'allowed_openapi_versions' => self::ALLOWED_OPENAPI_VERSIONS,
        ];
    }
}
