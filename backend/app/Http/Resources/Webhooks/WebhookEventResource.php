<?php

namespace BlastSpec\Http\Resources\Webhooks;

use BlastSpec\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WebhookEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var WebhookEvent $event */
        $event = $this->resource;

        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'idempotency_key' => $event->idempotency_key,
            'status' => $event->status,
            'signature_status' => $event->signature_status,
            'spec_version_id' => $event->spec_version_id,
            'diff_id' => $event->diff_id,
            'report_id' => $event->report_id,
            'error' => $event->error,
            'received_at' => $event->received_at?->toIso8601String(),
            'processed_at' => $event->processed_at?->toIso8601String(),
        ];
    }
}
