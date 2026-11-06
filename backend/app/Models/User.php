<?php

namespace BlastSpec\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

final class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use HasApiTokens;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'users';

    /** @var array<int, string> */
    protected $fillable = ['id', 'workspace_id', 'name', 'email', 'password_hash'];

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
