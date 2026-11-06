<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Actions\Services\CreateService;
use BlastSpec\Actions\Services\UpdateService;
use BlastSpec\Http\Requests\Services\CreateServiceRequest;
use BlastSpec\Http\Requests\Services\UpdateServiceRequest;
use BlastSpec\Http\Resources\Services\ServiceResource;
use BlastSpec\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

final class ServiceController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $services = Service::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->search($request->query('q'))
            ->orderBy('name')
            ->cursorPaginate((int) $request->query('limit', 25));

        return ServiceResource::collection($services);
    }

    public function store(CreateServiceRequest $request, CreateService $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $service = $action->execute(
            $workspaceId,
            $request->string('name')->toString(),
            $request->string('owner_team')->toString(),
            $request->input('repo_url'),
            $request->input('base_url'),
        );

        return (new ServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $serviceId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $service = Service::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->where('id', $serviceId)
            ->first();

        if ($service === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        return (new ServiceResource($service))->response();
    }

    public function update(UpdateServiceRequest $request, string $serviceId, UpdateService $action): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $service = Service::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->where('id', $serviceId)
            ->first();

        if ($service === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $updated = $action->execute($service, $request->validated());

        return (new ServiceResource($updated))->response();
    }

    public function destroy(Request $request, string $serviceId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $service = Service::query()
            ->forWorkspace($workspaceId)
            ->notDeleted()
            ->where('id', $serviceId)
            ->first();

        if ($service === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        $service->delete();

        return response()->noContent();
    }
}
