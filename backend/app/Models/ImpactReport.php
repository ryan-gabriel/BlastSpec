<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class ImpactReport extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'impact_reports';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'diff_id' => 'string',
        'graph_version' => 'string',
        'summary' => 'array',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'workspace_id', 'diff_id', 'status', 'strategy', 'graph_version',
        'summary', 'error', 'created_by', 'approved_by', 'approved_at',
    ];

    public function diff(): BelongsTo
    {
        return $this->belongsTo(ContractDiff::class, 'diff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImpactReportItem::class, 'report_id');
    }

    public function waves(): HasMany
    {
        return $this->hasMany(ImpactReportWave::class, 'report_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(ImpactReportCycle::class, 'report_id');
    }

    /** @param Builder<ImpactReport> $query */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public static function createQueued(string $workspaceId, string $diffId, ?string $userId = null): self
    {
        return self::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'diff_id' => $diffId,
            'status' => 'queued',
            'strategy' => 'topological',
            'summary' => ['affected_services' => 0, 'critical_changes' => 0, 'waves' => 0, 'cycles' => 0],
            'created_by' => $userId,
        ]);
    }
}
