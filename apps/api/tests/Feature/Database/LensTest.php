<?php

// Checks what the lens.* migrations promise: a lens can never weight sub-variables, a lens
// score never appears without coverage and confidence, and only Laravel (nv_app) may write.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** A county planner lens config: variable weights only, as Bible §6.6 describes. */
const COUNTY_PLANNER_CONFIG = '{"variable_weights": {"1": 0.3, "2": 0.3, "3": 0.1, "4": 0.2, "5": 0.1}, "thresholds": [40, 70], "direction": {}}';

/**
 * Insert a lens and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertLens(array $overrides = []): string
{
    return (string) DB::table('lens.lenses')->insertGetId(array_merge([
        'name' => 'county_planner',
        'customer_type' => 'county',
        'config' => COUNTY_PLANNER_CONFIG,
        'version' => 1,
    ], $overrides));
}

/**
 * Insert a lens score for a new entity under a new lens.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertLensScore(array $overrides = []): void
{
    $row = array_merge(['score' => 64, 'colour_class' => 'amber', 'coverage' => 0.8, 'confidence' => 0.7], $overrides);
    $row['entity_id'] ??= insertCoreEntity();
    $row['lens_id'] ??= insertLens();

    DB::table('lens.lens_scores')->insert($row);
}

test('the laravel role can store a lens and a lens score', function () {
    $entityId = insertCoreEntity();
    DB::statement('SET LOCAL ROLE nv_app');

    insertLensScore(['entity_id' => $entityId]);

    expect(DB::table('lens.lens_scores')->count())->toBe(1);
});

test('only the laravel role may write lens tables', function (string $tableName, string $roleName, bool $canInsert, bool $canUpdate) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        'SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, $privilegeName]
    )->allowed;

    $isInsertAllowed = $privilege('INSERT');

    expect($isInsertAllowed)->toBe($canInsert);
    expect($privilege('UPDATE'))->toBe($canUpdate);
    expect($privilege('DELETE'))->toBeFalse();
})->with([
    'lenses, laravel' => ['lens.lenses', 'nv_app', true, true],
    'lenses, ingest' => ['lens.lenses', 'nv_ingest', false, false],
    'lenses, signals' => ['lens.lenses', 'nv_signals', false, false],
    'lens scores, laravel' => ['lens.lens_scores', 'nv_app', true, true],
    'lens scores, ingest' => ['lens.lens_scores', 'nv_ingest', false, false],
    'lens scores, signals' => ['lens.lens_scores', 'nv_signals', false, false],
]);

test('a lens with sub-variable weights is rejected', function () {
    $insertSubWeights = fn () => insertLens(['config' => '{"variable_weights": {}, "sub_weights": {"2.4": 0.5}}']);

    expect($insertSubWeights)->toThrow(QueryException::class, 'lenses_weights_variables_only_check');
});

test('a lens without variable weights is rejected', function () {
    $insertWithoutWeights = fn () => insertLens(['config' => '{"thresholds": [40, 70]}']);

    expect($insertWithoutWeights)->toThrow(QueryException::class, 'lenses_weights_variables_only_check');
});

test('the same lens name and version cannot be stored twice', function () {
    insertLens();

    $insertSameVersion = fn () => insertLens();

    expect($insertSameVersion)->toThrow(QueryException::class, 'lenses_name_version_unique');
});

test('a lens score without its confidence is rejected', function () {
    $insertWithoutConfidence = fn () => insertLensScore(['confidence' => null]);

    expect($insertWithoutConfidence)->toThrow(QueryException::class, 'lens_scores_score_has_confidence_check');
});

test('a lens score without its coverage is rejected', function () {
    $insertWithoutCoverage = fn () => insertLensScore(['coverage' => null]);

    expect($insertWithoutCoverage)->toThrow(QueryException::class, 'not-null');
});

test('a lens score above 100 is rejected', function () {
    $insertTooHigh = fn () => insertLensScore(['score' => 120]);

    expect($insertTooHigh)->toThrow(QueryException::class, 'lens_scores_ranges_check');
});

test('a second lens score for the same lens and entity is rejected', function () {
    $entityId = insertCoreEntity();
    $lensId = insertLens();
    insertLensScore(['entity_id' => $entityId, 'lens_id' => $lensId]);

    $insertDuplicate = fn () => insertLensScore(['entity_id' => $entityId, 'lens_id' => $lensId]);

    expect($insertDuplicate)->toThrow(QueryException::class, 'lens_scores_lens_id_entity_id_unique');
});
