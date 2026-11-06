<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApiToken extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'api_tokens';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'workspace_id' => 'string',
        'user_id' => 'string',
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
