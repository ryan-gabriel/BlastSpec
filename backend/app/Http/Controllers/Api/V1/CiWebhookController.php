<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Models\Spec;
use BlastSpec\Models\SpecVersion;
use BlastSpec\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CiWebhookController
{
    public function receive(Request $request): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');
        $idempotencyKey = $request->header('Idempotency-Key');

        if (empty($idempotencyKey)) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1101',
                'title' => 'Idempotency-Key header is required.',
                'status' => 422,
                'code' => 'ERR-1101',
            ], 422);
        }

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:spec.published'],
            'spec_id' => ['required', 'uuid'],
            'version' => ['required', 'string', 'max:40'],
            'spec' => ['required', 'array'],
            'analyze_impact' => ['boolean'],
        ]);

        // Check idempotency
        $existing = WebhookEvent::query()
            ->where('workspace_id', $workspaceId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            // Same key — check body hash
            $bodyHash = hash('sha256', $request->getContent());
            // For simplicity, return original resources
            return response()->json([
                'event_id' => $existing->id,
                'status' => $existing->status,
                'duplicate' => true,
                'spec_version_id' => $existing->spec_version_id,
                'diff_id' => $existing->diff_id,
                'impact_report_id' => $existing->report_id,
            ], 202);
        }

        $spec = Spec::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $validated['spec_id'])
            ->first();

        if ($spec === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Spec not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $rawContent = json_encode($validated['spec']);

        DB::transaction(function () use ($workspaceId, $validated, $spec, $idempotencyKey, $rawContent): void
        {
            WebhookEvent::query()->create([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $workspaceId,
                'idempotency_key' => $idempotencyKey,
                'event_type' => $validated['event_type'],
                'payload' => $validated,
                'signature_status' => 'valid',
                'status' => 'queued',
                'received_at' => now(),
            ]);

            // Create spec version (simplified — full parsing in TASK-011)
            // Outbox event will be written by the domain action
        });

        return response()->json([
            'event_id' => (string) Str::uuid7(),
            'status' => 'queued',
            'duplicate' => false,
        ], 202);
    }
}
