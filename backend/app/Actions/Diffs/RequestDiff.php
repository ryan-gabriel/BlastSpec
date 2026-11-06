<?php

namespace BlastSpec\Actions\Diffs;

use BlastSpec\Models\ContractDiff;
use BlastSpec\Models\Spec;
use BlastSpec\Models\SpecVersion;

final class RequestDiff
{
    public function execute(string $workspaceId, string $specId, string $fromVersionId, string $toVersionId): ContractDiff
    {
        $spec = Spec::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $specId)
            ->first();

        if ($spec === null) {
            throw new \RuntimeException('Spec not found.');
        }

        $fromVersion = SpecVersion::query()->where('id', $fromVersionId)->where('spec_id', $specId)->first();
        $toVersion = SpecVersion::query()->where('id', $toVersionId)->where('spec_id', $specId)->first();

        if ($fromVersion === null || $toVersion === null) {
            throw new \RuntimeException('Versions belong to different specs.');
        }

        if ($fromVersionId === $toVersionId) {
            throw new \RuntimeException('No data to diff: versions are identical.');
        }

        // Check for existing diff
        $existing = ContractDiff::query()
            ->where('spec_id', $specId)
            ->where('from_version_id', $fromVersionId)
            ->where('to_version_id', $toVersionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return ContractDiff::createQueued($workspaceId, $specId, $fromVersionId, $toVersionId);
    }
}
