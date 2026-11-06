<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Http\Resources\Services\ServiceResource;
use BlastSpec\Models\ConsumerBinding;
use BlastSpec\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ConsumerBindingController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $query = ConsumerBinding::query()->forWorkspace($workspaceId);

        if ($request->query('provider_service_id')) {
            $query->where('provider_service_id', $request->query('provider_service_id'));
        }
        if ($request->query('consumer_service_id')) {
            $query->where('consumer_service_id', $request->query('consumer_service_id'));
        }

        $bindings = $query->with(['providerService', 'consumerService'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate((int) $request->query('limit', 25));

        return \BlastSpec\Http\Resources\ConsumerBindings\ConsumerBindingResource::collection($bindings);
    }

    public function store(Request $request): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');
        $user = $request->user();

        $validated = $request->validate([
            'provider_service_id' => ['required', 'uuid'],
            'consumer_service_id' => ['required', 'uuid'],
            'method' => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS,TRACE'],
            'path' => ['required', 'string', 'regex:/^\//'],
        ]);

        if ($validated['provider_service_id'] === $validated['consumer_service_id']) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1101',
                'title' => 'Provider and consumer must be different services.',
                'status' => 422,
                'code' => 'ERR-1101',
            ], 422);
        }

        // Check services exist in workspace
        $provider = Service::query()->forWorkspace($workspaceId)->where('id', $validated['provider_service_id'])->first();
        $consumer = Service::query()->forWorkspace($workspaceId)->where('id', $validated['consumer_service_id'])->first();

        if ($provider === null || $consumer === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Service not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        try {
            $binding = ConsumerBinding::createManual(
                $workspaceId,
                $validated['provider_service_id'],
                $validated['consumer_service_id'],
                $validated['method'],
                $validated['path'],
                $user?->id,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1103',
                'title' => 'Binding already exists.',
                'status' => 409,
                'code' => 'ERR-1103',
            ], 409);
        }

        $binding->load(['providerService', 'consumerService']);

        return (new \BlastSpec\Http\Resources\ConsumerBindings\ConsumerBindingResource($binding))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, string $bindingId): JsonResponse
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $binding = ConsumerBinding::query()
            ->forWorkspace($workspaceId)
            ->where('id', $bindingId)
            ->first();

        if ($binding === null) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1102',
                'title' => 'Resource not found.',
                'status' => 404,
                'code' => 'ERR-1102',
            ], 404);
        }

        if ($binding->source !== 'manual') {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1103',
                'title' => 'Only manual bindings can be removed.',
                'status' => 409,
                'code' => 'ERR-1103',
            ], 409);
        }

        $binding->delete();

        return response()->noContent();
    }
}
