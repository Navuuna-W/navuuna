<?php

// Checks ADR-010 DEC-08: a 2.x sub-variable with a finding viewers cannot see yet is reported as
// under review, so the rollup leaves it out of V2. Public and closed findings are not.

declare(strict_types=1);

use App\Engine\SubVariablesUnderReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('a sub-variable with a hidden open finding is under review', function (string $state) {
    $entityId = insertCoreEntity();
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.4', 'state' => $state]);

    $subIds = (new SubVariablesUnderReview(DB::connection()))->forEntity($entityId);

    expect($subIds)->toBe(['2.4']);
})->with(['detected', 'held', 'explanation_checked', 'contested']);

test('a published or closed finding does not hold back its sub-variable', function (string $state) {
    $entityId = insertCoreEntity();
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.4', 'state' => $state]);

    $subIds = (new SubVariablesUnderReview(DB::connection()))->forEntity($entityId);

    expect($subIds)->toBe([]);
})->with(['published', 'dismissed', 'resolved']);

test('every sub-variable under review is listed once, in order', function () {
    $entityId = insertCoreEntity();
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.4', 'state' => 'held']);
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'state' => 'explanation_checked']);
    insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'state' => 'dismissed']);

    $subIds = (new SubVariablesUnderReview(DB::connection()))->forEntity($entityId);

    expect($subIds)->toBe(['2.2', '2.4']);
});

test('another entity\'s findings are never included', function () {
    $entityId = insertCoreEntity();
    insertFinding(['sub_id' => '2.4', 'state' => 'held']);

    $subIds = (new SubVariablesUnderReview(DB::connection()))->forEntity($entityId);

    expect($subIds)->toBe([]);
});
