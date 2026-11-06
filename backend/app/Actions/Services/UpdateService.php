<?php

namespace BlastSpec\Actions\Services;

use BlastSpec\Models\Service;

final class UpdateService
{
    public function execute(Service $service, array $attributes): Service
    {
        $allowed = ['owner_team', 'repo_url', 'base_url'];
        $data = array_intersect_key($attributes, array_flip($allowed));

        if ($data === []) {
            return $service;
        }

        $service->fill($data);
        $service->save();

        return $service->fresh();
    }
}
