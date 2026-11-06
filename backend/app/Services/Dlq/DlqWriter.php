<?php

namespace BlastSpec\Services\Dlq;

use BlastSpec\Models\DlqJob;

final class DlqWriter
{
    public function write(
        string $workspaceId,
        string $jobType,
        array $payload,
        string $error,
        int $retryCount = 3,
    ): DlqJob
    {
        return DlqJob::createFromFailure($workspaceId, $jobType, $payload, $error, $retryCount);
    }
}
