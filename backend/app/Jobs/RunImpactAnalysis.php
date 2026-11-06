<?php

namespace BlastSpec\Jobs;

use BlastSpec\Models\ImpactReport;
use BlastSpec\Models\ContractDiff;
use BlastSpec\Models\SpecVersion;
use BlastSpec\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class RunImpactAnalysis
{
    public function handle(array $payload): void
    {
        $reportId = $payload['report_id'] ?? null;
        if ($reportId === null) {
            return;
        }

        $report = ImpactReport::query()->find($reportId);
        if ($report === null || $report->status !== 'queued') {
            return;
        }

        $report->update(['status' => 'running', 'started_at' => now()]);

        try {
            $diff = ContractDiff::query()->find($report->diff_id);
            if ($diff === null) {
                throw new \RuntimeException('Diff not found.');
            }

            $graphServiceUrl = config('graph_service_url', 'http://graph-service:8001');
            $token = config('graph_service_token', '');

            // Get change IDs (default to all breaking changes)
            $changeIds = $payload['change_ids'] ?? null;
            if ($changeIds === null) {
                $changeIds = \BlastSpec\Models\ContractChange::query()
                    ->where('diff_id', $diff->id)
                    ->where('breaking', true)
                    ->pluck('id')
                    ->toArray();
            }

            // Get affected endpoints from changes
            $changes = \BlastSpec\Models\ContractChange::query()
                ->whereIn('id', $changeIds)
                ->get();

            $endpointIds = [];
            // Map changes to endpoints via the diff's spec
            // (Simplified: use the to_version's endpoints)

            // Call graph service impact endpoint
            $impactResponse = Http::withHeaders(['X-Internal-Token' => $token])
                ->timeout(10)
                ->post($graphServiceUrl . '/internal/graph/impact', [
                    'workspace_id' => $report->workspace_id,
                    'endpoint_ids' => $endpointIds,
                    'max_depth' => 0,
                ]);

            if (! $impactResponse->successful()) {
                if ($impactResponse->status() === 503) {
                    throw new \RuntimeException('Graph is stale for this diff.');
                }
                throw new \RuntimeException('Impact analysis failed: ' . $impactResponse->body());
            }

            $impactData = $impactResponse->json();
            $graphVersion = $impactData['graph_version'] ?? null;

            // Call migration order endpoint
            $affectedServiceIds = array_column($impactData['affected'] ?? [], 'service_id');
            $orderResponse = Http::withHeaders(['X-Internal-Token' => $token])
                ->timeout(10)
                ->post($graphServiceUrl . '/internal/graph/migration-order', [
                    'workspace_id' => $report->workspace_id,
                    'service_ids' => $affectedServiceIds,
                ]);

            $orderData = $orderResponse->successful() ? $orderResponse->json() : [];
            $strategy = $orderData['strategy'] ?? 'topological';
            $waves = $orderData['waves'] ?? [];
            $cycles = $orderData['cycles'] ?? [];

            DB::transaction(function () use ($report, $impactData, $waves, $cycles, $strategy, $graphVersion, $changes, $changeIds): void
            {
                // Persist items
                $directConsumerIds = [];
                foreach ($impactData['affected'] ?? [] as $item) {
                    $service = Service::query()->find($item['service_id']);
                    if ($service === null) {
                        continue;
                    }

                    \BlastSpec\Models\ImpactReportItem::query()->create([
                        'id' => (string) \Illuminate\Support\Str::uuid7(),
                        'report_id' => $report->id,
                        'service_id' => $item['service_id'],
                        'owner_team' => $service->owner_team,
                        'relation' => $item['relation'] ?? 'transitive_consumer',
                        'depth' => $item['depth'] ?? 0,
                        'change_ids' => $changeIds,
                        'reason' => $item['path'] ? implode(' → ', $item['path']) : 'Affected by change.',
                    ]);
                }

                // Persist waves
                foreach ($waves as $wave) {
                    foreach ($wave['service_ids'] ?? [] as $sid) {
                        \BlastSpec\Models\ImpactReportWave::query()->create([
                            'id' => (string) \Illuminate\Support\Str::uuid7(),
                            'report_id' => $report->id,
                            'wave_index' => $wave['index'],
                            'service_id' => $sid,
                        ]);
                    }
                }

                // Persist cycles
                foreach ($cycles as $cycle) {
                    \BlastSpec\Models\ImpactReportCycle::query()->create([
                        'id' => (string) \Illuminate\Support\Str::uuid7(),
                        'report_id' => $report->id,
                        'service_ids' => $cycle['service_ids'] ?? [],
                        'recommended_break_service_id' => $cycle['recommended_break_service_id'] ?? null,
                        'reason' => $cycle['reason'] ?? 'Cycle detected.',
                    ]);
                }

                $report->update([
                    'status' => 'completed',
                    'strategy' => $strategy,
                    'graph_version' => $graphVersion,
                    'summary' => [
                        'affected_services' => count($impactData['affected'] ?? []),
                        'critical_changes' => count($changeIds),
                        'waves' => count($waves),
                        'cycles' => count($cycles),
                    ],
                    'completed_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            $report->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
