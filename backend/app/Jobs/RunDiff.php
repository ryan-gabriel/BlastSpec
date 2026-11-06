<?php

namespace BlastSpec\Jobs;

use BlastSpec\Models\ContractChange;
use BlastSpec\Models\ContractDiff;
use BlastSpec\Models\SpecVersion;
use BlastSpec\Services\Diff\DiffEngine;
use Illuminate\Support\Facades\DB;

final class RunDiff
{
    public function __construct(private readonly DiffEngine $engine) {}

    public function handle(array $payload): void
    {
        $diffId = $payload['diff_id'] ?? null;
        if ($diffId === null) {
            return;
        }

        $diff = ContractDiff::query()->find($diffId);
        if ($diff === null || $diff->status !== 'queued') {
            return;
        }

        $diff->update(['status' => 'running', 'started_at' => now()]);

        try {
            $fromVersion = SpecVersion::query()->find($diff->from_version_id);
            $toVersion = SpecVersion::query()->find($diff->to_version_id);

            if ($fromVersion === null || $toVersion === null) {
                throw new \RuntimeException('Spec versions not found.');
            }

            $changes = $this->engine->diff(
                $diff->workspace_id,
                $diff->id,
                $fromVersion->normalized ?? [],
                $toVersion->normalized ?? [],
            );

            DB::transaction(function () use ($diff, $changes): void
            {
                foreach ($changes as $change) {
                    ContractChange::createChange(
                        $diff->workspace_id,
                        $diff->id,
                        $change['fingerprint'],
                        $change['rule_id'],
                        $change['kind'],
                        $change['severity'],
                        $change['method'],
                        $change['path'],
                        $change['pointer'],
                        $change['before'],
                        $change['after'],
                        $change['description'],
                    );
                }

                $summary = [
                    'critical' => count(array_filter($changes, fn ($c) => $c['severity'] === 'critical')),
                    'warning' => count(array_filter($changes, fn ($c) => $c['severity'] === 'warning')),
                    'info' => count(array_filter($changes, fn ($c) => $c['severity'] === 'info')),
                    'total' => count($changes),
                    'breaking' => count(array_filter($changes, fn ($c) => $c['severity'] === 'critical')) > 0,
                ];

                $diff->update([
                    'status' => 'completed',
                    'summary' => $summary,
                    'completed_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            $diff->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
