<?php

namespace BlastSpec\Http\Resources\Dlq;

use BlastSpec\Models\DlqJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DlqJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DlqJob $job */
        $job = $this->resource;

        return [
            'id' => $job->id,
            'job_type' => $job->job_type,
            'payload' => $job->payload,
            'error' => $job->error,
            'retry_count' => $job->retry_count,
            'status' => $job->status,
            'last_attempt_at' => $job->last_attempt_at?->toIso8601String(),
            'replayed_at' => $job->replayed_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
        ];
    }
}
