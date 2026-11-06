<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Actions\Diffs\RequestDiff;
use BlastSpec\Http\Resources\Diffs\ContractDiffResource;
use BlastSpec\Http\Resources\Diffs\ContractChangeResource;
use BlastSpec\Models\ContractDiff;
use BlastSpec\Models\ContractChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DiffController
{
    public function store(Request $request, RequestDiff $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $validated = $request->validate([
            'spec_id' => ['required', 'uuid'],
            'from_version_id' => ['required', 'uuid'],
            'to_version_id' => ['required', 'uuid'],
        ]);

        try {
            $diff = $action->execute(
                $workspaceId,
                $validated['spec_id'],
                $validated['from_version_id'],
                $validated['to_version_id'],
            );
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'different specs')) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1302',
                    'title' => $message,
                    'status' => 422,
                    'code' => 'ERR-1302',
                ], 422);
            }
            if (str_contains($message, 'No data to diff') || str_contains($message, 'identical')) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1303',
                    'title' => $message,
                    'status' => 422,
                    'code' => 'ERR-1303',
                ], 422);
            }
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1201',
                'title' => $message,
                'status' => 422,
                'code' => 'ERR-1201',
            ], 422);
        }

        // Queue the diff job via outbox (simplified: dispatch directly)
        // In production, this would write an outbox row and the worker would process it.
        // For now, transition to queued and let the async handler pick it up.

        return response()->json([
            'id' => $diff->id,
            'spec_id' => $diff->spec_id,
            'from_version_id' => $diff->from_version_id,
            'to_version_id' => $diff->to_version_id,
            'status' => $diff->status,
            'engine_version' => $diff->engine_version,
            'summary' => $diff->summary,
            'created_at' => $diff->created_at?->toIso8601String(),
        ], 202)->header('Location', '/api/v1/diffs/' . $diff->id);
    }

    public function show(Request $request, string $diffId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $diff = ContractDiff::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $diffId)
            ->first();

        if ($diff === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        return response()->json([
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
        ]);
    }

    public function changes(Request $request, string $diffId): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $diff = ContractDiff::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $diffId)
            ->first();

        if ($diff === null) {
            abort(404);
        }

        $query = ContractChange::query()->where('diff_id', $diffId);

        if ($request->query('severity')) {
            $query->where('severity', $request->query('severity'));
        }
        if ($request->has('breaking')) {
            $query->where('breaking', filter_var($request->query('breaking'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->query('method')) {
            $query->where('method', $request->query('method'));
        }
        if ($request->query('path')) {
            $query->where('path', 'like', '%' . $request->query('path') . '%');
        }

        $changes = $query->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderBy('path')
            ->orderBy('method')
            ->orderBy('fingerprint')
            ->cursorPaginate((int) $request->query('limit', 25));

        return ContractChangeResource::collection($changes);
    }
}
