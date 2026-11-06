<?php

namespace BlastSpec\Http\Resources\Diffs;

use BlastSpec\Models\ContractChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContractChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ContractChange $change */
        $change = $this->resource;

        return [
            'id' => $change->id,
            'diff_id' => $change->diff_id,
            'fingerprint' => $change->fingerprint,
            'rule_id' => $change->br_rule_id,
            'kind' => $change->kind,
            'severity' => $change->severity,
            'breaking' => $change->breaking,
            'method' => $change->method,
            'path' => $change->path,
            'pointer' => $change->pointer,
            'before' => $change->before,
            'after' => $change->after,
            'description' => $change->description,
        ];
    }
}
