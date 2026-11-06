<?php

namespace Database\Seeders;

use BlastSpec\Models\Service;
use BlastSpec\Models\User;
use BlastSpec\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'demo'],
            [
                'id' => (string) Str::uuid7(),
                'name' => 'Demo workspace',
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'demo@blastspec.dev'],
            [
                'id' => (string) Str::uuid7(),
                'workspace_id' => $workspace->id,
                'name' => 'Demo User',
                'password_hash' => Hash::make('demo-password'),
            ],
        );

        $services = [
            ['name' => 'billing', 'owner_team' => 'Payments', 'base_url' => 'https://billing.internal.acme.dev'],
            ['name' => 'checkout', 'owner_team' => 'Storefront', 'base_url' => 'https://checkout.internal.acme.dev'],
            ['name' => 'notifications', 'owner_team' => 'Growth', 'base_url' => 'https://notifications.internal.acme.dev'],
        ];

        foreach ($services as $svc) {
            Service::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'slug' => $svc['name']],
                [
                    'id' => (string) Str::uuid7(),
                    'workspace_id' => $workspace->id,
                    'name' => $svc['name'],
                    'slug' => $svc['name'],
                    'owner_team' => $svc['owner_team'],
                    'base_url' => $svc['base_url'],
                ],
            );
        }
    }
}
