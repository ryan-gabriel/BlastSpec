<?php

namespace BlastSpec\Http\Resources\Services;

use BlastSpec\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource;

        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'owner_team' => $service->owner_team,
            'repo_url' => $service->repo_url,
            'base_url' => $service->base_url,
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }
}
