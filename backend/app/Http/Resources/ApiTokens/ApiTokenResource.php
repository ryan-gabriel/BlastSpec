<?php

namespace BlastSpec\Http\Resources\ApiTokens;

use BlastSpec\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ApiToken $token */
        $token = $this->resource;

        return [
            'id' => $token->id,
            'name' => $token->name,
            'prefix' => $token->prefix,
            'scopes' => $token->scopes ?? [],
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'revoked_at' => $token->revoked_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
