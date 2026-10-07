<?php

// Checks that the rollup reads the newest sub-variable score per sub-variable, keeps
// null_not_measured rows as "not measured", and never mixes in another entity's scores.

declare(strict_types=1);

use App\Engine\NewestSubVariableScores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the newest row per sub-variable is the input and older rows are ignored', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 20.0, computedAt: '2026-10-06 10:00:00+00');
    $newestRowId = addSubVariableScore($entityId, '1.2', 80.0, 0.9, computedAt: '2026-10-07 10:00:00+00');

    $inputs = (new NewestSubVariableScores(DB::connection()))->forEntity($entityId);

    expect($inputs)->toHaveCount(1)
        ->and($inputs['1.2']->score)->toBe(80.0)
        ->and($inputs['1.2']->confidence)->toBe(0.9)
        ->and($inputs['1.2']->sourceRowId)->toBe($newestRowId);
});

test('rows written in the same instant are ordered by their UUIDv7 id', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 20.0);
    addSubVariableScore($entityId, '1.2', 80.0);

    $inputs = (new NewestSubVariableScores(DB::connection()))->forEntity($entityId);

    expect($inputs['1.2']->score)->toBe(80.0);
});

test('a newer null_not_measured row replaces an older measured one', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 80.0, computedAt: '2026-10-06 10:00:00+00');
    addSubVariableScore($entityId, '1.2', null, computedAt: '2026-10-07 10:00:00+00');

    $inputs = (new NewestSubVariableScores(DB::connection()))->forEntity($entityId);

    expect($inputs['1.2']->score)->toBeNull()
        ->and($inputs['1.2']->confidence)->toBeNull()
        ->and($inputs['1.2']->isMeasured())->toBeFalse();
});

test('another entity\'s scores are never included', function () {
    $entityId = insertCoreEntity();
    $otherEntityId = insertCoreEntity();
    addSubVariableScore($entityId, '1.2', 80.0);
    addSubVariableScore($otherEntityId, '1.3', 50.0);

    $inputs = (new NewestSubVariableScores(DB::connection()))->forEntity($entityId);

    expect(array_keys($inputs))->toBe(['1.2']);
});

test('an entity with no scores has no inputs', function () {
    $entityId = insertCoreEntity();

    $inputs = (new NewestSubVariableScores(DB::connection()))->forEntity($entityId);

    expect($inputs)->toBe([]);
});
