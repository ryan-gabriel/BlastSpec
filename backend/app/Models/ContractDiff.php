<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class ContractDiff extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'contract_diffs';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'spec_id' => 'string',
        'from_version_id' => 'string',
        'to_version_id' => 'string',
        'summary' => 'array',
        'created_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'workspace_id', 'spec_id', 'from_version_id', 'to_version_id',
        'status', 'engine_version', 'summary', 'error',
    ];

    public function spec(): BelongsTo
    {
        return $this->belongsTo(Spec::class);
    }

    public function fromVersion(): BelongsTo
    {
        return $this->belongsTo(SpecVersion::class, 'from_version_id');
    }

    public function toVersion(): BelongsTo
    {
        return $this->belongsTo(SpecVersion::class, 'to_version_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ContractChange::class, 'diff_id');
    }

    /** @param Builder<ContractDiff> $query */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public static function createQueued(
        string $workspaceId,
        string $specId,
        string $fromVersionId,
        string $toVersionId,
    ): self
    {
        return self::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'spec_id' => $specId,
            'from_version_id' => $fromVersionId,
            'to_version_id' => $toVersionId,
            'status' => 'queued',
            'engine_version' => '1.0.0',
            'summary' => ['critical' => 0, 'warning' => 0, 'info' => 0, 'total' => 0, 'breaking' => false],
        ]);
    }
}
