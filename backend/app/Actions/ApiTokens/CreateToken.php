<?php

namespace BlastSpec\Actions\ApiTokens;

use BlastSpec\Models\ApiToken;
use BlastSpec\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

final class CreateToken
{
    /** @param array<int, string> $scopes */
    public function execute(string $workspaceId, string $name, array $scopes): array
    {
        if ($scopes === []) {
            throw new \InvalidArgumentException('At least one token scope is required.');
        }

        $rawSecret = bin2hex(random_bytes(16));
        $prefix = 'bs_' . substr(md5($rawSecret . random_bytes(8)), 0, 8);
        $token = $prefix . '_' . $rawSecret;

        $tokenRecord = ApiToken::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => null,
            'name' => $name,
            'prefix' => $prefix,
            'token_hash' => Hash::make($token),
            'scopes' => array_values(array_unique($scopes)),
        ]);

        return [
            'token' => [
                'id' => $tokenRecord->id,
                'name' => $tokenRecord->name,
                'prefix' => $tokenRecord->prefix,
                'scopes' => $tokenRecord->scopes,
                'created_at' => $tokenRecord->created_at?->toIso8601String(),
            ],
            'plaintext' => $token,
        ];
    }
}
