<?php

namespace BlastSpec\Http\Resources\ConsumerBindings;

use BlastSpec\Models\ConsumerBinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConsumerBindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ConsumerBinding $binding */
        $binding = $this->resource;

        return [
            'id' => $binding->id,
            'provider_service_id' => $binding->provider_service_id,
            'consumer_service_id' => $binding->consumer_service_id,
            'method' => $binding->method,
            'path' => $binding->path,
            'source' => $binding->source,
            'confidence' => $binding->confidence,
            'evidence' => $binding->evidence,
            'created_at' => $binding->created_at?->toIso8601String(),
            'provider_service' => $binding->providerService !== null ? [
                'id' => $binding->providerService->id,
                'name' => $binding->providerService->name,
                'slug' => $binding->providerService->slug,
            ] : null,
            'consumer_service' => $binding->consumerService !== null ? [
                'id' => $binding->consumerService->id,
                'name' => $binding->consumerService->name,
                'slug' => $binding->consumerService->slug,
            ] : null,
        ];
    }
}
