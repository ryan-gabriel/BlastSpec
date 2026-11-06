<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ContractChange extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'contract_changes';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'diff_id' => 'string',
        'before' => 'array',
        'after' => 'array',
        'breaking' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'workspace_id', 'diff_id', 'fingerprint', 'br_rule_id', 'kind',
        'severity', 'breaking', 'method', 'path', 'pointer', 'before', 'after', 'description',
    ];

    public function diff(): BelongsTo
    {
        return $this->belongsTo(ContractDiff::class, 'diff_id');
    }

    /** @param Builder<ContractChange> $query */
    public function scopeForDiff(Builder $query, string $diffId): Builder
    {
        return $query->where('diff_id', $diffId);
    }

    public static function createChange(
        string $workspaceId,
        string $diffId,
        string $fingerprint,
        string $ruleId,
        string $kind,
        string $severity,
        ?string $method,
        ?string $path,
        ?string $pointer,
        mixed $before,
        mixed $after,
        string $description,
    ): self
    {
        return self::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'diff_id' => $diffId,
            'fingerprint' => $fingerprint,
            'br_rule_id' => $ruleId,
            'kind' => $kind,
            'severity' => $severity,
            'breaking' => $severity === 'critical',
            'method' => $method,
            'path' => $path,
            'pointer' => $pointer,
            'before' => $before,
            'after' => $after,
            'description' => $description,
        ]);
    }
}
