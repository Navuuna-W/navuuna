<?php

// Checks the rollup's stream reader against a real Redis (ADR-004a §3): a batch message rolls up
// its entities and is acknowledged; a failed rollup leaves it pending; a message a crashed reader
// left behind is taken over; an unreadable one is logged and acknowledged so it cannot loop.

declare(strict_types=1);

use App\Engine\BatchWrittenConsumer;
use App\Engine\RollupEntity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\Client as PredisClient;

uses(RefreshDatabase::class);

/** Reads in tests never wait for new messages. */
const NO_WAIT_MILLISECONDS = 1;

/**
 * The raw Redis client, so test commands skip Laravel's key prefix exactly like the consumer.
 */
function rawRedis(): PredisClient
{
    $client = Redis::connection()->client();
    assert($client instanceof PredisClient);

    return $client;
}

/**
 * Publish a signals.batch_written message with the fields the Python runner sends.
 *
 * @param  string  $entityIdsJson  the entity_ids field as sent: a JSON array of UUID strings
 */
function publishBatch(string $entityIdsJson): void
{
    rawRedis()->executeRaw([
        'XADD', BatchWrittenConsumer::STREAM, '*',
        'batch_id', '0192a000-0000-7000-8000-000000000000',
        'entity_ids', $entityIdsJson,
        'module', 'water',
        'adapter_versions', '{"1.2":"1.0.0"}',
        'written_at', '2026-10-08T10:00:00Z',
    ]);
}

function pendingMessageCount(): int
{
    $summary = rawRedis()->executeRaw(['XPENDING', BatchWrittenConsumer::STREAM, BatchWrittenConsumer::GROUP]);

    return is_array($summary) ? (int) $summary[0] : 0;
}

function makeConsumer(int $pendingTakeoverMilliseconds = BatchWrittenConsumer::PENDING_TAKEOVER_MILLISECONDS): BatchWrittenConsumer
{
    return new BatchWrittenConsumer(app('redis'), app(RollupEntity::class), Log::getFacadeRoot(), $pendingTakeoverMilliseconds);
}

beforeEach(function () {
    rawRedis()->executeRaw(['DEL', BatchWrittenConsumer::STREAM]);
    makeConsumer()->createGroupIfMissing();
});

test('a batch message rolls up each entity and is acknowledged', function () {
    $firstEntityId = insertCoreEntity();
    $secondEntityId = insertCoreEntity();
    publishBatch(json_encode([$firstEntityId, $secondEntityId], JSON_THROW_ON_ERROR));

    $handledCount = makeConsumer()->processOnce('worker-a', NO_WAIT_MILLISECONDS);

    expect($handledCount)->toBe(1)
        ->and(DB::table('scores.variable_scores')->where('entity_id', $firstEntityId)->count())->toBe(5)
        ->and(DB::table('scores.variable_scores')->where('entity_id', $secondEntityId)->count())->toBe(5)
        ->and(pendingMessageCount())->toBe(0);
});

test('a message whose rollup fails stays pending', function () {
    $entityId = insertCoreEntity();
    // Make every variable_scores insert fail; RefreshDatabase rolls this back after the test.
    DB::statement('ALTER TABLE scores.variable_scores ADD CONSTRAINT test_block_inserts CHECK (false) NOT VALID');
    publishBatch(json_encode([$entityId], JSON_THROW_ON_ERROR));

    $processOnce = fn () => makeConsumer()->processOnce('worker-a', NO_WAIT_MILLISECONDS);

    expect($processOnce)->toThrow(QueryException::class)
        ->and(pendingMessageCount())->toBe(1);
});

test('a message left pending by a crashed reader is taken over', function () {
    $entityId = insertCoreEntity();
    publishBatch(json_encode([$entityId], JSON_THROW_ON_ERROR));
    // A reader takes the message and dies before acknowledging it.
    rawRedis()->executeRaw([
        'XREADGROUP', 'GROUP', BatchWrittenConsumer::GROUP, 'crashed-worker',
        'COUNT', '1', 'STREAMS', BatchWrittenConsumer::STREAM, '>',
    ]);

    $handledCount = makeConsumer(pendingTakeoverMilliseconds: 0)->processOnce('worker-b', NO_WAIT_MILLISECONDS);

    expect($handledCount)->toBe(1)
        ->and(DB::table('scores.variable_scores')->where('entity_id', $entityId)->count())->toBe(5)
        ->and(pendingMessageCount())->toBe(0);
});

test('a message pending for less than 5 minutes is left to its reader', function () {
    publishBatch(json_encode([insertCoreEntity()], JSON_THROW_ON_ERROR));
    rawRedis()->executeRaw([
        'XREADGROUP', 'GROUP', BatchWrittenConsumer::GROUP, 'busy-worker',
        'COUNT', '1', 'STREAMS', BatchWrittenConsumer::STREAM, '>',
    ]);

    $handledCount = makeConsumer()->processOnce('worker-b', NO_WAIT_MILLISECONDS);

    expect($handledCount)->toBe(0)
        ->and(pendingMessageCount())->toBe(1);
});

test('an unreadable message is logged and acknowledged', function (string $entityIdsJson) {
    $log = Log::spy();
    publishBatch($entityIdsJson);

    $handledCount = makeConsumer()->processOnce('worker-a', NO_WAIT_MILLISECONDS);

    expect($handledCount)->toBe(1)
        ->and(pendingMessageCount())->toBe(0)
        ->and(DB::table('scores.variable_scores')->count())->toBe(0);
    $log->shouldHaveReceived('error')->once();
})->with([
    'not JSON' => ['not json'],
    'a JSON object, not a list' => ['{"id": "x"}'],
    'an id that is not a UUID' => ['["entity-1"]'],
]);

test('an unknown entity id is skipped and the message still acknowledged', function () {
    publishBatch('["0192a000-0000-7000-8000-00000000abcd"]');

    makeConsumer()->processOnce('worker-a', NO_WAIT_MILLISECONDS);

    expect(pendingMessageCount())->toBe(0);
});

test('creating the group again is harmless', function () {
    $createGroupAgain = fn () => makeConsumer()->createGroupIfMissing();

    expect($createGroupAgain)->not->toThrow(RuntimeException::class);
});

test('nothing to read handles nothing', function () {
    $handledCount = makeConsumer()->processOnce('worker-a', NO_WAIT_MILLISECONDS);

    expect($handledCount)->toBe(0);
});
