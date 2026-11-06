<?php

namespace BlastSpec\Console\Commands;

use BlastSpec\Models\Outbox;
use BlastSpec\Services\Outbox\StreamPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OutboxRelay extends Command
{
    protected $signature = 'outbox:relay {--once : Run a single batch and exit}';

    protected $description = 'Relay pending outbox rows to Redis Streams.';

    public function handle(StreamPublisher $publisher): int
    {
        $batchSize = (int) config('outbox.batch_size', 50);
        $maxAttempts = (int) config('outbox.max_attempts', 10);
        $pollMs = (int) config('outbox.poll_interval_ms', 1000);

        $streams = [
            'spec.version.created' => 'graph',
            'diff.requested' => 'diff',
            'impact.requested' => 'impact',
            'webhook.received' => 'webhook',
        ];

        $this->info('Outbox relay started.');

        do {
            $processed = $this->processBatch($publisher, $batchSize, $maxAttempts, $streams);

            if ($this->option('once')) {
                break;
            }

            if ($processed === 0) {
                usleep($pollMs * 1000);
            }
        } while (true);

        return self::SUCCESS;
    }

    /** @param array<string, string> $streams */
    private function processBatch(StreamPublisher $publisher, int $batchSize, int $maxAttempts, array $streams): int
    {
        $rows = DB::transaction(function () use ($batchSize): \Illuminate\Support\Collection
        {
            return Outbox::query()
                ->pending()
                ->lockForUpdate()
                ->orderBy('available_at')
                ->limit($batchSize)
                ->get();
        });

        $processed = 0;

        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($row, $publisher, $streams): void
                {
                    $locked = Outbox::query()
                        ->where('id', $row->id)
                        ->where('status', 'pending')
                        ->lockForUpdate()
                        ->first();

                    if ($locked === null) {
                        return;
                    }

                    $locked->update(['status' => 'processing', 'attempts' => $locked->attempts + 1]);

                    $stream = $streams[$locked->event_type] ?? 'graph';
                    $publisher->publish($stream, [
                        'outbox_id' => $locked->id,
                        'event_type' => $locked->event_type,
                        'aggregate_type' => $locked->aggregate_type,
                        'aggregate_id' => $locked->aggregate_id,
                        'payload' => json_encode($locked->payload),
                    ]);

                    $locked->update(['status' => 'processed', 'processed_at' => now()]);
                });

                $processed++;
            } catch (\Throwable $e) {
                $row->update([
                    'status' => $row->attempts >= $maxAttempts ? 'failed' : 'pending',
                    'last_error' => $e->getMessage(),
                    'available_at' => now()->addSeconds(min(30, 2 ** $row->attempts)),
                ]);
            }
        }

        return $processed;
    }
}
