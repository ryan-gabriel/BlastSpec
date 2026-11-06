<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Actions\Specs\UploadSpec;
use BlastSpec\Http\Requests\Specs\UploadSpecRequest;
use BlastSpec\Http\Requests\Specs\UploadSpecVersionRequest;
use BlastSpec\Http\Resources\Specs\SpecDetailResource;
use BlastSpec\Http\Resources\Specs\SpecResource;
use BlastSpec\Http\Resources\Specs\SpecVersionResource;
use BlastSpec\Models\Service;
use BlastSpec\Models\Spec;
use BlastSpec\Models\SpecVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class SpecController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $specs = Spec::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->when($request->query('service_id'), fn ($q, $v) => $q->where('service_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where('name', 'like', '%' . $v . '%'))
            ->with(['service', 'currentVersion'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate((int) $request->query('limit', 25));

        return SpecResource::collection($specs);
    }

    public function store(UploadSpecRequest $request, UploadSpec $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');
        $user = $request->user();

        $file = $request->uploadedFile();
        if ($file === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1106',
                'title' => 'Unsupported media type.',
                'status' => 415,
                'code' => 'ERR-1106',
            ], 415);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['json', 'yaml', 'yml'], true)) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1106',
                'title' => 'Unsupported media type.',
                'status' => 415,
                'code' => 'ERR-1106',
            ], 415);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1203',
                'title' => 'Spec exceeds size limit.',
                'status' => 413,
                'code' => 'ERR-1203',
            ], 413);
        }

        $rawContent = (string) file_get_contents($file->getRealPath());

        try {
            $result = $action->execute(
                $workspaceId,
                $request->string('service_id')->toString(),
                (string) $request->input('name', ''),
                $request->string('version')->toString(),
                $rawContent,
                $user,
                $extension,
            );
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'already exists')) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1205',
                    'title' => $message,
                    'status' => 409,
                    'code' => 'ERR-1205',
                ], 409);
            }
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1201',
                'title' => $message,
                'status' => 422,
                'code' => 'ERR-1201',
                'errors' => [['pointer' => '/', 'message' => $message]],
            ], 422);
        }

        /** @var Spec $spec */
        $spec = $result['spec'];
        $spec->load(['service', 'currentVersion']);

        return (new SpecDetailResource($spec))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $specId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $spec = Spec::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->where('id', $specId)
            ->with(['service', 'currentVersion'])
            ->first();

        if ($spec === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        return (new SpecDetailResource($spec))->response();
    }

    public function destroy(Request $request, string $specId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $spec = Spec::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
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

        // Check for active (non-approved) impact reports referencing this spec
        if (\Illuminate\Support\Facades\Schema::hasTable('impact_reports')) {
            $activeReport = \Illuminate\Support\Facades\DB::table('impact_reports')
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', ['queued', 'running', 'completed'])
                ->whereExists(function ($q) use ($specId) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('contract_diffs')
                        ->whereColumn('contract_diffs.id', 'impact_reports.diff_id')
                        ->where('contract_diffs.spec_id', $specId);
                })
                ->first();

            if ($activeReport !== null) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1207',
                    'title' => 'Spec is referenced by an active report.',
                    'status' => 409,
                    'code' => 'ERR-1207',
                ], 409);
            }
        }

        $spec->delete();

        return response()->noContent();
    }

    public function versions(Request $request, string $specId): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $spec = Spec::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->where('id', $specId)
            ->first();

        if ($spec === null) {
            abort(404);
        }

        $versions = SpecVersion::query()
            ->where('spec_id', $spec->id)
            ->orderBy('created_at', 'desc')
            ->cursorPaginate((int) $request->query('limit', 25));

        return SpecVersionResource::collection($versions);
    }

    public function uploadVersion(UploadSpecVersionRequest $request, string $specId, UploadSpec $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');
        $user = $request->user();

        $spec = Spec::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->where('id', $specId)
            ->with('service')
            ->first();

        if ($spec === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $file = $request->uploadedFile();
        if ($file === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1106',
                'title' => 'Unsupported media type.',
                'status' => 415,
                'code' => 'ERR-1106',
            ], 415);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['json', 'yaml', 'yml'], true)) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1106',
                'title' => 'Unsupported media type.',
                'status' => 415,
                'code' => 'ERR-1106',
            ], 415);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1203',
                'title' => 'Spec exceeds size limit.',
                'status' => 413,
                'code' => 'ERR-1203',
            ], 413);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        $rawContent = (string) file_get_contents($file->getRealPath());

        try {
            $result = $action->execute(
                $workspaceId,
                $spec->service_id,
                $spec->name,
                $request->string('version')->toString(),
                $rawContent,
                $user,
                $extension,
            );
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'already exists') || str_contains($message, 'Duplicate')) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1205',
                    'title' => $message,
                    'status' => 409,
                    'code' => 'ERR-1205',
                ], 409);
            }
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1201',
                'title' => $message,
                'status' => 422,
                'code' => 'ERR-1201',
                'errors' => [['pointer' => '/', 'message' => $message]],
            ], 422);
        }

        return (new SpecVersionResource($result['version']))
            ->response()
            ->setStatusCode(201);
    }
}
