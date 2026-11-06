<?php

namespace BlastSpec\Http\Resources\Specs;

use BlastSpec\Models\Spec;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SpecDetailResource extends JsonResource
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
            'service' => $spec->service !== null ? [
                'id' => $spec->service->id,
                'name' => $spec->service->name,
                'slug' => $spec->service->slug,
                'owner_team' => $spec->service->owner_team,
                'repo_url' => $spec->service->repo_url,
                'base_url' => $spec->service->base_url,
            ] : null,
            'current_version' => $spec->currentVersion !== null
                ? (new SpecVersionResource($spec->currentVersion))->resolve($request)
                : null,
            'versions_count' => $spec->versions()->count(),
            'created_at' => $spec->created_at?->toIso8601String(),
            'updated_at' => $spec->updated_at?->toIso8601String(),
        ];
    }
}
