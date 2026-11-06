<?php

namespace BlastSpec\Http\Resources\Impact;

use BlastSpec\Models\ImpactReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ImpactReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ImpactReport $report */
        $report = $this->resource;

        return [
            'id' => $report->id,
            'diff_id' => $report->diff_id,
            'status' => $report->status,
            'strategy' => $report->strategy,
            'graph_version' => $report->graph_version,
            'summary' => $report->summary,
            'error' => $report->error,
            'created_at' => $report->created_at?->toIso8601String(),
            'completed_at' => $report->completed_at?->toIso8601String(),
            'approved_at' => $report->approved_at?->toIso8601String(),
            'approved_by' => $report->approved_by,
            'items' => $report->items->map(fn ($item) => [
                'service_id' => $item->service_id,
                'service_name' => $item->service?->name ?? '',
                'owner_team' => $item->owner_team,
                'relation' => $item->relation,
                'depth' => $item->depth,
                'change_ids' => $item->change_ids,
                'reason' => $item->reason,
            ])->values()->all(),
            'migration_waves' => $report->waves->groupBy('wave_index')->map(fn ($items, $index) => [
                'index' => (int) $index,
                'service_ids' => $items->pluck('service_id')->values()->all(),
                'service_names' => $items->map(fn ($w) => $w->service?->name ?? '')->values()->all(),
            ])->values()->all(),
            'cycles' => $report->cycles->map(fn ($c) => [
                'service_ids' => $c->service_ids,
                'service_names' => [],
                'recommended_break_service_id' => $c->recommended_break_service_id,
                'reason' => $c->reason,
            ])->values()->all(),
        ];
    }
}
