<?php

namespace BlastSpec\Http\Resources\Diffs;

use BlastSpec\Models\ContractDiff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContractDiffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ContractDiff $diff */
        $diff = $this->resource;

        return [
            'id' => $diff->id,
            'spec_id' => $diff->spec_id,
            'from_version_id' => $diff->from_version_id,
            'to_version_id' => $diff->to_version_id,
            'status' => $diff->status,
            'engine_version' => $diff->engine_version,
            'summary' => $diff->summary,
            'error' => $diff->error,
            'created_at' => $diff->created_at?->toIso8601String(),
            'completed_at' => $diff->completed_at?->toIso8601String(),
        ];
    }
}
