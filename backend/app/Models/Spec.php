<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Spec extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'specs';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'service_id' => 'string',
        'current_version_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = ['id', 'name', 'slug', 'service_id', 'workspace_id', 'current_version_id'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SpecVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SpecVersion::class);
    }

    /** @param Builder<Spec> $query */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /** @param Builder<Spec> $query */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public static function slugify(string $name): string
    {
        return Str::slug($name);
    }
}
