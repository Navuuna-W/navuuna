<?php

// Checks the 10-minute sweep (ADR-004a §4): it finds exactly the entities whose variable scores
// are behind their sub-variable scores, rolls them up, and leaves up-to-date or retired ones alone.

declare(strict_types=1);

use App\Engine\ReconciliationSweep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Append a variable score for an entity at a given time, as an earlier rollup would have.
 */
function addVariableScoreAt(string $entityId, string $computedAt): void
{
    DB::table('scores.variable_scores')->insert([
        'entity_id' => $entityId, 'variable_id' => 3, 'score' => null, 'coverage' => 0,
        'confidence' => null, 'status' => 'cannot_assess', 'gate_status' => null,
        'weight_version' => 1, 'engine_version' => '1.0.0', 'inputs' => '{}', 'computed_at' => $computedAt,
    ]);
}

test('an entity with sub-variable scores and no variable score is stale', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 80.0);

    $staleEntityIds = app(ReconciliationSweep::class)->findStaleEntityIds();

    expect($staleEntityIds)->toBe([$entityId]);
});

test('an entity with a newer sub-variable score than variable score is stale', function () {
    $entityId = insertCoreEntity();
    addVariableScoreAt($entityId, '2026-10-07 09:00:00+00');
    addSubVariableScore($entityId, '1.2', 80.0, computedAt: '2026-10-07 10:00:00+00');

    $staleEntityIds = app(ReconciliationSweep::class)->findStaleEntityIds();

    expect($staleEntityIds)->toBe([$entityId]);
});

test('an up-to-date entity is not stale', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 80.0, computedAt: '2026-10-07 10:00:00+00');
    addVariableScoreAt($entityId, '2026-10-07 11:00:00+00');

    $staleEntityIds = app(ReconciliationSweep::class)->findStaleEntityIds();

    expect($staleEntityIds)->toBe([]);
});

test('retired entities and entities with no sub-variable scores are never stale', function () {
    $retiredEntityId = insertCoreEntity(['retired_at' => now()]);
    addSubVariableScore($retiredEntityId, '1.2', 80.0);
    insertCoreEntity();

    $staleEntityIds = app(ReconciliationSweep::class)->findStaleEntityIds();

    expect($staleEntityIds)->toBe([]);
});

test('running the sweep rolls up each stale entity once', function () {
    $staleEntityId = insertCoreEntity();
    addSubVariableScore($staleEntityId, '1.2', 80.0, computedAt: '2026-10-07 10:00:00+00');
    $upToDateEntityId = insertCoreEntity();
    addSubVariableScore($upToDateEntityId, '1.2', 80.0, computedAt: '2026-10-07 10:00:00+00');
    addVariableScoreAt($upToDateEntityId, '2026-10-07 11:00:00+00');

    $rolledUpCount = app(ReconciliationSweep::class)->run();

    expect($rolledUpCount)->toBe(1)
        ->and(DB::table('scores.variable_scores')->where('entity_id', $staleEntityId)->count())->toBe(5)
        ->and(DB::table('scores.variable_scores')->where('entity_id', $upToDateEntityId)->count())->toBe(1);
});

test('engine:sweep reports how many entities it rolled up', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 80.0);

    $exitCode = Artisan::call('engine:sweep');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Swept 1 stale entities.');
});
