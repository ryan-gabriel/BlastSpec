<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Workspace extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'workspaces';

    /** @var array<int, string> */
    protected $fillable = ['id', 'name', 'slug'];

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @var array<int, HasMany> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public static function createNew(array $attributes): self
    {
        return self::query()->create(array_merge($attributes, ['id' => (string) Str::uuid7()]));
    }
}
