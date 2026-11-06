<?php

namespace BlastSpec\Actions\Dlq;

use BlastSpec\Models\DlqJob;
use Illuminate\Support\Facades\DB;

final class ReplayDlqJob
{
    public function execute(DlqJob $job): DlqJob
    {
        if (! $job->isPending()) {
            throw new \RuntimeException('This job was already replayed or discarded.');
        }

        DB::transaction(function () use ($job): void
        {
            $job->markReplayed();
            // Re-dispatch the job with the original payload.
            // The actual dispatch mechanism (outbox/stream) is handled by the caller.
        });

        return $job->fresh();
    }
}
