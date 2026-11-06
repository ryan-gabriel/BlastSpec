<?php

namespace BlastSpec\Http\Resources\Specs;

use BlastSpec\Models\Spec;
use BlastSpec\Models\SpecVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SpecVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SpecVersion $version */
        $version = $this->resource;

        return [
            'id' => $version->id,
            'spec_id' => $version->spec_id,
            'version' => $version->version,
            'content_hash' => $version->content_hash,
            'openapi_version' => $version->openapi_version,
            'parse_status' => $version->parse_status,
            'endpoint_count' => $version->endpoint_count,
            'warning_count' => $version->warning_count,
            'parse_error' => $version->parse_error,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
