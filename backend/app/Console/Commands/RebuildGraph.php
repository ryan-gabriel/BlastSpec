<?php

namespace BlastSpec\Console\Commands;

use BlastSpec\Services\Graph\GraphSyncService;
use BlastSpec\Services\Graph\SnapshotBuilder;
use Illuminate\Console\Command;

final class RebuildGraph extends Command
{
    protected $signature = 'graph:rebuild {--all : Rebuild all workspaces} {--workspace= : Specific workspace ID}';

    protected $description = 'Rebuild the Kuzu graph from Postgres data.';

    public function handle(SnapshotBuilder $builder, GraphSyncService $sync): int
    {
        if ($this->option('all')) {
            $workspaces = \BlastSpec\Models\Workspace::query()->get();
        } elseif ($this->option('workspace')) {
            $workspaces = \BlastSpec\Models\Workspace::query()
                ->where('id', $this->option('workspace'))
                ->get();
        } else {
            $this->error('Specify --all or --workspace=<id>.');

            return self::FAILURE;
        }

        foreach ($workspaces as $workspace) {
            $this->info("Rebuilding graph for workspace: {$workspace->name} ({$workspace->id})");

            try {
                $snapshot = $builder->build($workspace->id);
                $result = $sync->sync($snapshot);
                $this->info("  Done. graph_version={$result['graph_version']}, nodes={$result['nodes']}, edges={$result['edges']}");
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
