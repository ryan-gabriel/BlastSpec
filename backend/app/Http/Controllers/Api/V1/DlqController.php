<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Actions\Dlq\ReplayDlqJob;
use BlastSpec\Http\Resources\Dlq\DlqJobResource;
use BlastSpec\Models\DlqJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DlqController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $jobs = DlqJob::query()
            ->where('workspace_id', $workspaceId)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('created_at', 'desc')
            ->cursorPaginate((int) $request->query('limit', 25));

        return DlqJobResource::collection($jobs);
    }

    public function replay(Request $request, string $jobId, ReplayDlqJob $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $job = DlqJob::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $jobId)
            ->first();

        if ($job === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        try {
            $job = $action->execute($job);
        } catch (\RuntimeException $e) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1103',
                'title' => $e->getMessage(),
                'status' => 409,
                'code' => 'ERR-1103',
            ], 409);
        }

        return (new DlqJobResource($job))->response();
    }

    public function discard(Request $request, string $jobId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $job = DlqJob::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $jobId)
            ->first();

        if ($job === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $job->markDiscarded();

        return response()->noContent();
    }
}
