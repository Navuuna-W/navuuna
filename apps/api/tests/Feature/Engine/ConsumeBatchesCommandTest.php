<?php

// Checks `engine:consume-batches --once`: it creates the rollup group, rolls up the entities in a
// waiting signals.batch_written message and exits. A PHPUnit class so PHPStan can type artisan().

declare(strict_types=1);

namespace Tests\Feature\Engine;

use App\Engine\BatchWrittenConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Predis\Client as PredisClient;
use Tests\TestCase;

class ConsumeBatchesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_once_rolls_up_a_waiting_batch_and_exits(): void
    {
        $redis = Redis::connection()->client();
        $this->assertInstanceOf(PredisClient::class, $redis);
        $redis->executeRaw(['DEL', BatchWrittenConsumer::STREAM]);
        $entityId = insertCoreEntity();
        $redis->executeRaw(['XADD', BatchWrittenConsumer::STREAM, '*', 'entity_ids', json_encode([$entityId])]);

        $this->artisan('engine:consume-batches', ['--once' => true, '--consumer-name' => 'test', '--block-ms' => 1])
            ->expectsOutput('Reading signals.batch_written as test.')
            ->expectsOutput('Handled 1 message(s).')
            ->assertSuccessful();

        $this->assertSame(5, DB::table('scores.variable_scores')->where('entity_id', $entityId)->count());
    }
}
