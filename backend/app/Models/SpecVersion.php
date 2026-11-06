<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SpecVersion extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'spec_versions';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'spec_id' => 'string',
        'normalized' => 'array',
        'warnings' => 'array',
        'endpoint_count' => 'integer',
        'warning_count' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'id', 'spec_id', 'workspace_id', 'version', 'raw_content', 'content_hash',
        'openapi_version', 'normalized', 'parse_status', 'parse_error',
        'warnings', 'endpoint_count', 'warning_count', 'created_by',
    ];

    public function spec(): BelongsTo
    {
        return $this->belongsTo(Spec::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<SpecVersion> $query */
    public function scopeForSpec(Builder $query, string $specId): Builder
    {
        return $query->where('spec_id', $specId);
    }

    public static function hashContent(string $content): string
    {
        return hash('sha256', $content);
    }
}
