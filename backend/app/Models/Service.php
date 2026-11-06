<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Service extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'services';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @var array<int, string> */
    protected $fillable = ['id', 'name', 'slug', 'owner_team', 'repo_url', 'base_url', 'workspace_id'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @param Builder<Service> $query */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /** @param Builder<Service> $query */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($term)) . '%';

        return $query->where(function (Builder $q) use ($like): void
        {
            $q->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('owner_team', 'like', $like);
        });
    }

    /** @param Builder<Service> $query */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public static function slugify(string $name): string
    {
        return Str::slug($name);
    }
}
