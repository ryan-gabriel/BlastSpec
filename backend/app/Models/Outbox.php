<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class Outbox extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'outbox';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'aggregate_id' => 'string',
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'workspace_id', 'event_type', 'aggregate_type', 'aggregate_id',
        'payload', 'status', 'attempts', 'available_at', 'processed_at', 'last_error',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @param Builder<Outbox> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('available_at', '<=', now());
    }

    public static function write(
        string $workspaceId,
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        array $payload,
    ): self
    {
        return self::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
        ]);
    }
}
