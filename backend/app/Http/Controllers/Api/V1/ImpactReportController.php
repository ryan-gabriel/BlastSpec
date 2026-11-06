<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Actions\Impact\CreateImpactReport;
use BlastSpec\Http\Resources\Impact\ImpactReportResource;
use BlastSpec\Models\ImpactReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ImpactReportController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $reports = ImpactReport::query()
            ->forWorkspace($workspaceId)
            ->when($request->query('diff_id'), fn ($q, $v) => $q->where('diff_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->with(['diff', 'items', 'waves', 'cycles'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate((int) $request->query('limit', 25));

        return ImpactReportResource::collection($reports);
    }

    public function store(Request $request, CreateImpactReport $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');
        $user = $request->user();

        $validated = $request->validate([
            'diff_id' => ['required', 'uuid'],
            'change_ids' => ['nullable', 'array'],
            'change_ids.*' => ['uuid'],
        ]);

        try {
            $report = $action->execute(
                $workspaceId,
                $validated['diff_id'],
                $validated['change_ids'] ?? null,
                $user?->id,
            );
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'not completed')) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1301',
                    'title' => $message,
                    'status' => 409,
                    'code' => 'ERR-1301',
                ], 409);
            }
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => $message,
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $report->load(['diff', 'items', 'waves', 'cycles']);

        return (new ImpactReportResource($report))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Request $request, string $reportId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $report = ImpactReport::query()
            ->forWorkspace($workspaceId)
            ->where('id', $reportId)
            ->with(['diff', 'items.service', 'waves', 'cycles'])
            ->first();

        if ($report === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        return (new ImpactReportResource($report))->response();
    }

    public function approve(Request $request, string $reportId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');
        $user = $request->user();

        $report = ImpactReport::query()
            ->forWorkspace($workspaceId)
            ->where('id', $reportId)
            ->first();

        if ($report === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        if ($report->status !== 'completed') {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1601',
                'title' => 'Impact report is not completed.',
                'status' => 409,
                'code' => 'ERR-1601',
            ], 409);
        }

        if ($report->approved_at !== null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1602',
                'title' => 'Impact report already approved.',
                'status' => 409,
                'code' => 'ERR-1602',
            ], 409);
        }

        $report->update([
            'status' => 'approved',
            'approved_by' => $user?->id,
            'approved_at' => now(),
        ]);

        $report->load(['diff', 'items.service', 'waves', 'cycles']);

        return (new ImpactReportResource($report))->response();
    }
}
