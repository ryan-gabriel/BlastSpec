<?php

namespace BlastSpec\Services\Outbox;

use BlastSpec\Models\Outbox;

final class OutboxWriter
{
    /**
     * Write an outbox row. Must be called inside a DB::transaction alongside the domain write.
     *
     * @param array<string, mixed> $payload
     */
    public function write(
        string $workspaceId,
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        array $payload,
    ): Outbox
    {
        return Outbox::write($workspaceId, $eventType, $aggregateType, $aggregateId, $payload);
    }
}
