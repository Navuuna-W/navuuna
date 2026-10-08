<?php

// Reads signals.batch_written, which the Python signal runner publishes after it commits a chunk
// of sub-variable scores, and rolls up every entity in each message. Run for ever by
// `engine:consume-batches` under Supervisor, so the rollup needs no manual command (K2.2).

declare(strict_types=1);

namespace App\Engine;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Str;
use Predis\Client as PredisClient;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Implements ADR-004a §3: consumer group `rollup`, XACK only after the database work has
 * committed, and XAUTOCLAIM of messages left pending for more than 5 minutes.
 */
final class BatchWrittenConsumer
{
    /** Stream and group names, exactly as services/signals/engine/batch_publisher.py uses them. */
    public const STREAM = 'signals.batch_written';

    public const GROUP = 'rollup';

    /** A message pending this long belongs to a reader that crashed; take it over (ADR-004a §3). */
    public const PENDING_TAKEOVER_MILLISECONDS = 300_000;

    /** Messages read per round trip. Each carries at most 500 entity ids. */
    private const MESSAGES_PER_READ = 10;

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly RollupEntity $rollupEntity,
        private readonly LoggerInterface $logger,
        private readonly int $pendingTakeoverMilliseconds = self::PENDING_TAKEOVER_MILLISECONDS,
    ) {}

    /**
     * Create the `rollup` group if it does not exist yet. It starts at id 0, so messages
     * published before the first reader started are still rolled up.
     */
    public function createGroupIfMissing(): void
    {
        try {
            $this->runRedisCommand(['XGROUP', 'CREATE', self::STREAM, self::GROUP, '0', 'MKSTREAM']);
        } catch (RuntimeException $exception) {
            // BUSYGROUP = the group already exists, which is the normal case after the first start.
            if (! str_starts_with($exception->getMessage(), 'BUSYGROUP')) {
                throw $exception;
            }
        }
    }

    /**
     * Handle one round: first take over stale messages, otherwise wait up to $blockMilliseconds
     * for new ones. Returns how many messages were handled.
     */
    public function processOnce(string $consumerName, int $blockMilliseconds): int
    {
        $messages = $this->claimStaleMessages($consumerName);
        if ($messages === []) {
            $messages = $this->readNewMessages($consumerName, $blockMilliseconds);
        }

        foreach ($messages as $messageId => $fields) {
            $this->handleMessage($messageId, $fields);
        }

        return count($messages);
    }

    /**
     * Roll up every entity in the message, then acknowledge it. RollupEntity commits each entity
     * on its own, so a crash part-way leaves the message pending; a second run is harmless
     * because the rollup is idempotent.
     *
     * @param  array<string, string>|null  $fields  null when the message was trimmed from the stream
     */
    private function handleMessage(string $messageId, ?array $fields): void
    {
        $entityIds = $this->readEntityIds($fields);
        if ($entityIds === null) {
            // A message we can never handle would come back every 5 minutes for ever.
            $this->logger->error('Skipping unreadable signals.batch_written message', ['message_id' => $messageId]);
        }

        foreach ($entityIds ?? [] as $entityId) {
            $this->rollupEntity->rollUp($entityId);
        }

        $this->runRedisCommand(['XACK', self::STREAM, self::GROUP, $messageId]);
    }

    /**
     * The `entity_ids` field: a JSON array of UUID strings (ADR-004a §3). Null if it is anything else.
     *
     * @param  array<string, string>|null  $fields
     * @return list<string>|null
     */
    private function readEntityIds(?array $fields): ?array
    {
        $decoded = json_decode($fields['entity_ids'] ?? '', true);
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return null;
        }

        foreach ($decoded as $entityId) {
            if (! is_string($entityId) || ! Str::isUuid($entityId)) {
                return null;
            }
        }

        return $decoded;
    }

    /**
     * @return array<string, array<string, string>|null> message id → fields
     */
    private function claimStaleMessages(string $consumerName): array
    {
        $reply = $this->runRedisCommand([
            'XAUTOCLAIM', self::STREAM, self::GROUP, $consumerName,
            (string) $this->pendingTakeoverMilliseconds, '0', 'COUNT', (string) self::MESSAGES_PER_READ,
        ]);

        // Reply (always, even when nothing is claimed): [next start id, [[id, [field, value, …]], …], [ids trimmed from the stream]].
        $messages = $this->toMessages($reply[1]);
        $trimmedMessageIds = $reply[2] ?? [];
        foreach ($trimmedMessageIds as $trimmedMessageId) {
            $messages[(string) $trimmedMessageId] = null;
        }

        return $messages;
    }

    /**
     * @return array<string, array<string, string>|null> message id → fields
     */
    private function readNewMessages(string $consumerName, int $blockMilliseconds): array
    {
        $reply = $this->runRedisCommand([
            'XREADGROUP', 'GROUP', self::GROUP, $consumerName,
            'COUNT', (string) self::MESSAGES_PER_READ, 'BLOCK', (string) $blockMilliseconds,
            'STREAMS', self::STREAM, '>',
        ]);

        // Reply: null when nothing arrived in time, else [[stream, [[id, [field, value, …]], …]]].
        if (! is_array($reply)) {
            return [];
        }

        return $this->toMessages($reply[0][1]);
    }

    /**
     * Turn Redis's flat [field, value, field, value] lists into field → value maps.
     *
     * @param  mixed  $entries  [[id, [field, value, …]], …]
     * @return array<string, array<string, string>|null>
     */
    private function toMessages(mixed $entries): array
    {
        $messages = [];
        foreach (is_array($entries) ? $entries : [] as [$messageId, $flatFields]) {
            $fields = [];
            foreach (array_chunk(is_array($flatFields) ? $flatFields : [], 2) as [$field, $value]) {
                $fields[(string) $field] = (string) $value;
            }
            $messages[(string) $messageId] = $fields;
        }

        return $messages;
    }

    /**
     * Send one command to Redis exactly as written. executeRaw skips Laravel's key prefix, so the
     * stream name matches the one Python writes.
     *
     * @param  list<string>  $arguments
     *
     * @throws RuntimeException when Redis answers with an error
     */
    private function runRedisCommand(array $arguments): mixed
    {
        $client = $this->redis->connection()->client();
        if (! $client instanceof PredisClient) {
            throw new RuntimeException('The rollup stream consumer needs REDIS_CLIENT=predis.');
        }

        $reply = $client->executeRaw($arguments, $isError);
        if ($isError) {
            throw new RuntimeException((string) $reply);
        }

        return $reply;
    }
}
