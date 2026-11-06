<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class ConsumerBinding extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'consumer_bindings';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'provider_service_id' => 'string',
        'consumer_service_id' => 'string',
        'confidence' => 'float',
        'evidence' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'workspace_id', 'provider_service_id', 'consumer_service_id',
        'method', 'path', 'source', 'confidence', 'evidence', 'created_by',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function providerService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'provider_service_id');
    }

    public function consumerService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'consumer_service_id');
    }

    /** @param Builder<ConsumerBinding> $query */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public static function createManual(
        string $workspaceId,
        string $providerServiceId,
        string $consumerServiceId,
        string $method,
        string $path,
        ?string $userId = null,
    ): self
    {
        return self::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'provider_service_id' => $providerServiceId,
            'consumer_service_id' => $consumerServiceId,
            'method' => strtoupper($method),
            'path' => $path,
            'source' => 'manual',
            'confidence' => 1.0,
            'evidence' => ['source' => 'manual'],
            'created_by' => $userId,
        ]);
    }
}
