<?php

namespace BlastSpec\Http\Resources\Specs;

use BlastSpec\Models\Spec;
use BlastSpec\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SpecResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Spec $spec */
        $spec = $this->resource;

        return [
            'id' => $spec->id,
            'service_id' => $spec->service_id,
            'name' => $spec->name,
            'slug' => $spec->slug,
            'current_version_id' => $spec->current_version_id,
            'created_at' => $spec->created_at?->toIso8601String(),
            'updated_at' => $spec->updated_at?->toIso8601String(),
        ];
    }
}
