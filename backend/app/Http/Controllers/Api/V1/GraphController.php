<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Services\Graph\GraphSyncService;
use BlastSpec\Services\Graph\SnapshotBuilder;
use BlastSpec\Models\Spec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class GraphController
{
    public function __construct(
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly GraphSyncService $syncService,
    ) {}

    public function show(Request $request, string $specId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $spec = Spec::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $specId)
            ->first();

        if ($spec === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $versionId = $request->query('version_id', $spec->current_version_id);

        $graphServiceUrl = config('graph_service_url', 'http://graph-service:8001');
        $token = config('graph_service_token', '');

        try {
            $response = Http::withHeaders(['X-Internal-Token' => $token])
                ->timeout(10)
                ->get($graphServiceUrl . '/internal/graph/workspace/' . $workspaceId, [
                    'version_id' => $versionId,
                    'depth' => $request->query('depth', 0),
                    'include_schemas' => $request->query('include_schemas', false),
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1401',
                    'title' => 'Graph service unavailable.',
                    'status' => 503,
                    'code' => 'ERR-1401',
                ], 503);
            }

            return response()->json($response->json());
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1401',
                'title' => 'Graph service unavailable.',
                'status' => 503,
                'code' => 'ERR-1401',
            ], 503);
        }
    }

    public function rebuild(Request $request, string $specId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $spec = Spec::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $specId)
            ->first();

        if ($spec === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        // Build snapshot and sync
        $snapshot = $this->snapshotBuilder->build($workspaceId);

        try {
            $result = $this->syncService->sync($snapshot);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1403',
                'title' => 'Graph sync failed.',
                'status' => 500,
                'code' => 'ERR-1403',
            ], 500);
        }

        return response()->json([
            'status' => 'queued',
            'job_id' => null,
            'resource_id' => $specId,
        ], 202);
    }
}
