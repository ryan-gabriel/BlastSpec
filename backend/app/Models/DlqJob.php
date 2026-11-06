<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DlqJob extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'dlq_jobs';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'payload' => 'array',
        'retry_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'replayed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'workspace_id', 'job_type', 'payload', 'error', 'retry_count',
        'status', 'last_attempt_at', 'replayed_at',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @param Builder<DlqJob> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markReplayed(): void
    {
        $this->update(['status' => 'replayed', 'replayed_at' => now()]);
    }

    public function markDiscarded(): void
    {
        $this->update(['status' => 'discarded', 'replayed_at' => now()]);
    }

    public static function createFromFailure(
        string $workspaceId,
        string $jobType,
        array $payload,
        string $error,
        int $retryCount = 3,
    ): self
    {
        return self::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'job_type' => $jobType,
            'payload' => $payload,
            'error' => $error,
            'retry_count' => $retryCount,
            'status' => 'pending',
            'last_attempt_at' => now(),
        ]);
    }
}
