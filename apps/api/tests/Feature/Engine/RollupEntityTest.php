<?php

// Checks that RollupEntity saves five variable scores per entity in one go, as the Laravel role,
// with the rules of Bible §6.3 and ADR-010 DEC-08 applied, and that doing it twice changes nothing.
// Uses the real weights.yml (V1: 1.2 0.40 · 1.3 0.25 · 1.4 0.25 · 1.5 0.10).

declare(strict_types=1);

use App\Engine\EntityScored;
use App\Engine\RollupEntity;
use App\Models\VariableScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * The newest stored score of one variable for an entity.
 */
function newestVariableScore(string $entityId, int $variableId): VariableScore
{
    return VariableScore::query()
        ->where('entity_id', $entityId)
        ->where('variable_id', $variableId)
        ->orderByDesc('computed_at')
        ->orderByDesc('id')
        ->firstOrFail();
}

test('an entity gets five variable scores with versions, coverage and confidence', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.1', 100.0, 0.7);
    addSubVariableScore($entityId, '1.2', 80.0, 1.0);
    DB::statement('SET LOCAL ROLE nv_app');

    $isRolledUp = app(RollupEntity::class)->rollUp($entityId);

    $activity = newestVariableScore($entityId, 1);
    expect($isRolledUp)->toBeTrue()
        ->and(DB::table('scores.variable_scores')->where('entity_id', $entityId)->count())->toBe(5)
        ->and($activity->status)->toBe('scored')
        ->and($activity->score)->toBe(80.0)
        ->and($activity->coverage)->toEqualWithDelta(0.40, 0.0001)
        ->and($activity->confidence)->toBe(1.0)
        ->and($activity->gate_status)->toBe('passed')
        ->and($activity->weight_version)->toBe(1)
        ->and($activity->engine_version)->toBe(RollupEntity::ENGINE_VERSION)
        ->and($activity->inputs)->toMatchArray(['measured_count' => 1, 'total_count' => 4]);
});

test('variables with nothing measured are stored as cannot_assess with 0 coverage', function () {
    $entityId = insertCoreEntity();

    app(RollupEntity::class)->rollUp($entityId);

    $activity = newestVariableScore($entityId, 1);
    $momentum = newestVariableScore($entityId, 3);
    expect($activity->status)->toBe('cannot_assess')
        ->and($activity->gate_status)->toBe('unmeasured')
        ->and($momentum->status)->toBe('cannot_assess')
        ->and($momentum->gate_status)->toBeNull()
        ->and($momentum->coverage)->toBe(0.0);
});

test('a failed gate is stored as cannot_assess naming the gate', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.1', 0.0, 0.7);
    addSubVariableScore($entityId, '1.2', 80.0, 1.0);

    app(RollupEntity::class)->rollUp($entityId);

    $activity = newestVariableScore($entityId, 1);
    expect($activity->status)->toBe('cannot_assess')
        ->and($activity->score)->toBeNull()
        ->and($activity->gate_failed_sub_id)->toBe('1.1');
});

test('an unmeasured gate is stored as provisional at half confidence', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 80.0, 0.8);

    app(RollupEntity::class)->rollUp($entityId);

    $activity = newestVariableScore($entityId, 1);
    expect($activity->status)->toBe('provisional')
        ->and($activity->confidence)->toEqualWithDelta(0.4, 0.0001);
});

test('a sub-variable under review is left out of V2 and listed as held back', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '2.1', 0.0, 0.9);
    addSubVariableScore($entityId, '2.2', 90.0, 1.0);
    addSubVariableScore($entityId, '2.4', 10.0, 1.0);
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'state' => 'held']);

    app(RollupEntity::class)->rollUp($entityId);

    $discrepancy = newestVariableScore($entityId, 2);
    expect($discrepancy->score)->toBe(10.0)
        ->and($discrepancy->coverage)->toEqualWithDelta(0.35, 0.0001)
        ->and($discrepancy->inputs)->toMatchArray(['held_back_sub_ids' => ['2.2']]);
});

test('a held finding never changes the other variables', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.1', 100.0, 0.7);
    addSubVariableScore($entityId, '1.2', 80.0, 1.0);
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'state' => 'held']);

    app(RollupEntity::class)->rollUp($entityId);

    $activity = newestVariableScore($entityId, 1);
    expect($activity->inputs)->toMatchArray(['held_back_sub_ids' => []]);
});

test('rolling up twice stores the same scores', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.1', 100.0, 0.7);
    addSubVariableScore($entityId, '1.2', 80.0, 1.0);
    $rollupEntity = app(RollupEntity::class);

    $rollupEntity->rollUp($entityId);
    $firstScores = DB::table('scores.variable_scores')->orderBy('variable_id')->pluck('score', 'variable_id');
    DB::table('scores.variable_scores')->delete();
    $rollupEntity->rollUp($entityId);
    $secondScores = DB::table('scores.variable_scores')->orderBy('variable_id')->pluck('score', 'variable_id');

    expect($secondScores->all())->toBe($firstScores->all());
});

test('a retired entity is not rolled up', function () {
    $entityId = insertCoreEntity(['retired_at' => now()]);
    addSubVariableScore($entityId, '1.2', 80.0, 1.0);

    $isRolledUp = app(RollupEntity::class)->rollUp($entityId);

    expect($isRolledUp)->toBeFalse()
        ->and(DB::table('scores.variable_scores')->count())->toBe(0);
});

test('EntityScored is announced once per rolled-up entity', function () {
    Event::fake([EntityScored::class]);
    $entityId = insertCoreEntity();

    app(RollupEntity::class)->rollUp($entityId);

    Event::assertDispatchedTimes(EntityScored::class, 1);
    Event::assertDispatched(EntityScored::class, fn (EntityScored $event) => $event->entityId === $entityId);
});
