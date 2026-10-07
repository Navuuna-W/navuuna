<?php

// Checks `engine:rollup`: one entity by id, every non-retired entity with --all, and a clear
// error for bad input. A PHPUnit class so PHPStan can type $this->artisan().

declare(strict_types=1);

namespace Tests\Feature\Engine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RollupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_entity_is_rolled_up_by_id(): void
    {
        $entityId = insertCoreEntity();

        $this->artisan('engine:rollup', ['entity' => $entityId])
            ->expectsOutput('Rolled up 1 entity.')
            ->assertSuccessful();

        $this->assertSame(5, DB::table('scores.variable_scores')->where('entity_id', $entityId)->count());
    }

    public function test_all_skips_retired_entities(): void
    {
        $firstEntityId = insertCoreEntity();
        $secondEntityId = insertCoreEntity();
        insertCoreEntity(['retired_at' => now()]);

        $this->artisan('engine:rollup', ['--all' => true])
            ->expectsOutput('Rolled up 2 entities.')
            ->assertSuccessful();

        $rolledUpEntityIds = DB::table('scores.variable_scores')->distinct()->orderBy('entity_id')->pluck('entity_id')->all();
        $expectedEntityIds = [$firstEntityId, $secondEntityId];
        sort($expectedEntityIds);
        $this->assertSame($expectedEntityIds, $rolledUpEntityIds);
    }

    public function test_a_retired_entity_by_id_fails(): void
    {
        $entityId = insertCoreEntity(['retired_at' => now()]);

        $this->artisan('engine:rollup', ['entity' => $entityId])
            ->expectsOutput('No entity with that id, or it is retired.')
            ->assertFailed();
    }

    public function test_an_entity_id_and_all_together_fail(): void
    {
        $entityId = insertCoreEntity();

        $this->artisan('engine:rollup', ['entity' => $entityId, '--all' => true])
            ->expectsOutput('Give one entity id, or --all, but not both.')
            ->assertFailed();
    }

    public function test_neither_an_entity_id_nor_all_fails(): void
    {
        $this->artisan('engine:rollup')
            ->expectsOutput('Give one entity id, or --all, but not both.')
            ->assertFailed();
    }
}
