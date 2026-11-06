<?php

namespace BlastSpec\Actions\Services;

use BlastSpec\Models\Service;
use Illuminate\Support\Str;

final class CreateService
{
    public function execute(string $workspaceId, string $name, string $ownerTeam, ?string $repoUrl = null, ?string $baseUrl = null): Service
    {
        $slug = Service::slugify($name);

        $existing = Service::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->first();

        if ($existing !== null) {
            throw new \RuntimeException('A service with this slug already exists in the workspace.');
        }

        return Service::query()->create([
            'id' => (string) Str::uuid7(),
            'workspace_id' => $workspaceId,
            'name' => $name,
            'slug' => $slug,
            'owner_team' => $ownerTeam,
            'repo_url' => $repoUrl,
            'base_url' => $baseUrl,
        ]);
    }
}
