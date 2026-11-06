<?php

namespace BlastSpec\Http\Controllers\Api\V1;

use BlastSpec\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WebhookEventController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = (string) $request->attributes->get('workspace_id', '');

        $events = WebhookEvent::query()
            ->forWorkspace($workspaceId)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('received_at', 'desc')
            ->cursorPaginate((int) $request->query('limit', 25));

        return \BlastSpec\Http\Resources\Webhooks\WebhookEventResource::collection($events);
    }
}
