<?php

namespace BlastSpec\Actions\Impact;

use BlastSpec\Models\ContractDiff;
use BlastSpec\Models\ImpactReport;
use Illuminate\Support\Str;

final class CreateImpactReport
{
    public function execute(string $workspaceId, string $diffId, ?array $changeIds = null, ?string $userId = null): ImpactReport
    {
        $diff = ContractDiff::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $diffId)
            ->first();

        if ($diff === null) {
            throw new \RuntimeException('Diff not found.');
        }

        if ($diff->status !== 'completed') {
            throw new \RuntimeException('Diff is not completed.');
        }

        return ImpactReport::createQueued($workspaceId, $diffId, $userId);
    }
}
