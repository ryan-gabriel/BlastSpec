<?php

namespace BlastSpec\Console\Commands;

use BlastSpec\Services\Outbox\StreamConsumer;
use Illuminate\Console\Command;

final class StreamsConsume extends Command
{
    protected $signature = 'streams:consume {streams* : Stream names to consume} {--group=workers : Consumer group name}';

    protected $description = 'Consume messages from Redis Streams with consumer groups.';

    public function handle(StreamConsumer $consumer): int
    {
        $streams = (array) $this->argument('streams');
        $group = (string) $this->option('group');
        $consumerName = 'worker-' . gethostname() . '-' . getmypid();

        foreach ($streams as $stream) {
            $consumer->ensureGroup($stream, $group);
        }

        $this->info('Consuming streams: ' . implode(', ', $streams));

        $backoff = [2, 10, 30];

        while (true) {
            foreach ($streams as $stream) {
                // Reclaim stale pending messages first
                $reclaimed = $consumer->reclaim($stream, $group, $consumerName);
                foreach ($reclaimed as $msg) {
                    $this->processMessage($consumer, $stream, $group, $consumerName, $msg, $backoff);
                }

                // Read new messages
                $messages = $consumer->read($stream, $group, $consumerName);
                foreach ($messages as $msg) {
                    $this->processMessage($consumer, $stream, $group, $consumerName, $msg, $backoff);
                }
            }
        }

        return self::SUCCESS;
    }

    /** @param array{id: string, fields: array<string, mixed>} $msg */
    private function processMessage(StreamConsumer $consumer, string $stream, string $group, string $consumerName, array $msg, array $backoff): void
    {
        $messageId = $msg['id'];
        $fields = $msg['fields'];

        $eventType = (string) ($fields['event_type'] ?? '');
        $outboxId = (string) ($fields['outbox_id'] ?? '');

        $attempt = 0;
        $maxAttempts = (int) config('stream.max_attempts', 3);

        while ($attempt < $maxAttempts) {
            try {
                // Dispatch to the appropriate handler based on event type
                $this->dispatch($eventType, $fields);

                // Ack only after successful processing
                $consumer->ack($stream, $group, $messageId);

                return;
            } catch (\Throwable $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    // Write to DLQ (basic — full DLQ is TASK-045)
                    $this->error("Failed to process {$messageId} after {$maxAttempts} attempts: " . $e->getMessage());

                    // Ack to prevent infinite reprocessing; DLQ row would be written here
                    $consumer->ack($stream, $group, $messageId);

                    return;
                }

                $delay = $backoff[min($attempt - 1, count($backoff) - 1)] ?? 30;
                sleep($delay);
            }
        }
    }

    /** @param array<string, mixed> $fields */
    private function dispatch(string $eventType, array $fields): void
    {
        // Handler dispatch is implemented per event type in subsequent tasks.
        // For now, log the event for observability.
        $this->line(sprintf('[%s] %s aggregate=%s', now()->toIso8601String(), $eventType, $fields['aggregate_id'] ?? ''));
    }
}
