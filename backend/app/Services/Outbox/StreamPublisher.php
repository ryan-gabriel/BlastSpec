<?php

namespace BlastSpec\Services\Outbox;

use Illuminate\Support\Facades\Redis;

final class StreamPublisher
{
    public function __construct(private readonly string $prefix = 'blastspec') {}

    /**
     * Publish a message to a Redis Stream.
     *
     * @param array<string, mixed> $fields
     */
    public function publish(string $stream, array $fields): string
    {
        $name = $this->prefix . ':' . $stream;

        /** @var string $id */
        $id = Redis::executeRaw(array_merge(['XADD', $name, '*'], $this->flattenFields($fields)));

        return $id;
    }

    /**
     * Ensure a consumer group exists for a stream.
     */
    public function ensureGroup(string $stream, string $group): void
    {
        $name = $this->prefix . ':' . $stream;

        try {
            Redis::executeRaw(['XGROUP', 'CREATE', $name, $group, '0', 'MKSTREAM']);
        } catch (\Throwable $e) {
            // Group already exists — ignore
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    /** @param array<string, mixed> $fields */
    private function flattenFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            $result[] = (string) $key;
            $result[] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return $result;
    }
}
