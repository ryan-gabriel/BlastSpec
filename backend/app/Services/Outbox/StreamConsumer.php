<?php

namespace BlastSpec\Services\Outbox;

use Illuminate\Support\Facades\Redis;

final class StreamConsumer
{
    public function __construct(
        private readonly string $prefix = 'blastspec',
        private readonly int $maxAttempts = 3,
        private readonly int $pendingTimeoutSeconds = 60,
    ) {}

    /**
     * Ensure the consumer group exists for a stream.
     */
    public function ensureGroup(string $stream, string $group): void
    {
        $name = $this->prefix . ':' . $stream;

        try {
            Redis::executeRaw(['XGROUP', 'CREATE', $name, $group, '0', 'MKSTREAM']);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Read a batch of messages from a stream using a consumer group.
     *
     * @return array<int, array{id: string, fields: array<string, mixed>}>
     */
    public function read(string $stream, string $group, string $consumer, int $count = 10, int $blockMs = 2000): array
    {
        $name = $this->prefix . ':' . $stream;

        $result = Redis::executeRaw(['XREADGROUP', 'GROUP', $group, $consumer, 'COUNT', (string) $count, 'BLOCK', (string) $blockMs, 'STREAMS', $name, '>']);

        if (! is_array($result) || empty($result)) {
            return [];
        }

        $messages = [];
        foreach ($result as $streamData) {
            if (! is_array($streamData) || count($streamData) < 2) {
                continue;
            }
            $entries = $streamData[1] ?? [];
            foreach ($entries as $entry) {
                if (! is_array($entry) || count($entry) < 2) {
                    continue;
                }
                $id = (string) $entry[0];
                $rawFields = $entry[1] ?? [];
                $fields = [];
                for ($i = 0; $i < count($rawFields); $i += 2) {
                    $fields[(string) $rawFields[$i]] = $rawFields[$i + 1] ?? null;
                }
                $messages[] = ['id' => $id, 'fields' => $fields];
            }
        }

        return $messages;
    }

    /**
     * Acknowledge a message after successful processing.
     */
    public function ack(string $stream, string $group, string $messageId): void
    {
        $name = $this->prefix . ':' . $stream;
        Redis::executeRaw(['XACK', $name, $group, $messageId]);
    }

    /**
     * Reclaim pending messages that have been idle too long.
     *
     * @return array<int, array{id: string, fields: array<string, mixed>}>
     */
    public function reclaim(string $stream, string $group, string $consumer, int $count = 10): array
    {
        $name = $this->prefix . ':' . $stream;

        $result = Redis::executeRaw(['XAUTOCLAIM', $name, $group, $consumer, (string) ($this->pendingTimeoutSeconds * 1000), '0-0', 'COUNT', (string) $count]);

        if (! is_array($result) || count($result) < 2 || ! is_array($result[1])) {
            return [];
        }

        $messages = [];
        foreach ($result[1] as $entry) {
            if (! is_array($entry) || count($entry) < 2) {
                continue;
            }
            $id = (string) $entry[0];
            $rawFields = $entry[1] ?? [];
            $fields = [];
            for ($i = 0; $i < count($rawFields); $i += 2) {
                $fields[(string) $rawFields[$i]] = $rawFields[$i + 1] ?? null;
            }
            $messages[] = ['id' => $id, 'fields' => $fields];
        }

        return $messages;
    }

    /**
     * Get pending message count for a stream/group.
     */
    public function pendingCount(string $stream, string $group): int
    {
        $name = $this->prefix . ':' . $stream;

        $info = Redis::xpending($name, $group);

        return is_array($info) && isset($info[0]) ? (int) $info[0] : 0;
    }
}
