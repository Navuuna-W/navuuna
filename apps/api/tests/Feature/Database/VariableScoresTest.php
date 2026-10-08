<?php

// Checks what the scores.variable_scores migration promises: the three-state gate of Bible §6.3
// is enforced by the database, a score never appears without coverage and confidence, and only
// Laravel (nv_app) may append rows, which nobody may change.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert a variable score. By default a V1 score whose gate passed.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertVariableScore(array $overrides = []): void
{
    $row = array_merge([
        'variable_id' => 1,
        'score' => 72,
        'coverage' => 0.75,
        'confidence' => 0.6,
        'status' => 'scored',
        'gate_status' => 'passed',
        'weight_version' => 1,
        'engine_version' => '0.1.0',
        'inputs' => '{}',
    ], $overrides);
    $row['entity_id'] ??= insertCoreEntity();

    DB::table('scores.variable_scores')->insert($row);
}

/** A V1 whose gate failed: no score, and the failing gate named. */
const FAILED_GATE_FIELDS = [
    'status' => 'cannot_assess', 'gate_status' => 'failed', 'gate_failed_sub_id' => '1.1',
    'score' => null, 'confidence' => null,
];

/** A V1 whose gate could not be measured: scored from what is there, marked provisional. */
const UNMEASURED_GATE_FIELDS = ['status' => 'provisional', 'gate_status' => 'unmeasured', 'confidence' => 0.3];

/** V3 has no gate, so it has no gate status. */
const UNGATED_VARIABLE_FIELDS = ['variable_id' => 3, 'gate_status' => null];

test('the laravel role can append each of the three gate outcomes and an ungated variable', function () {
    $entityId = insertCoreEntity();
    DB::statement('SET LOCAL ROLE nv_app');

    insertVariableScore(['entity_id' => $entityId]);
    insertVariableScore(array_merge(['entity_id' => $entityId], FAILED_GATE_FIELDS));
    insertVariableScore(array_merge(['entity_id' => $entityId], UNMEASURED_GATE_FIELDS));
    insertVariableScore(array_merge(['entity_id' => $entityId], UNGATED_VARIABLE_FIELDS));

    expect(DB::table('scores.variable_scores')->count())->toBe(4);
});

test('an unmeasured gate with nothing measured can be stored as cannot_assess', function () {
    $entityId = insertCoreEntity();
    DB::statement('SET LOCAL ROLE nv_app');

    insertVariableScore([
        'entity_id' => $entityId, 'status' => 'cannot_assess', 'gate_status' => 'unmeasured',
        'score' => null, 'confidence' => null, 'coverage' => 0,
    ]);

    expect(DB::table('scores.variable_scores')->count())->toBe(1);
});

test('only the laravel role may insert, and no role may update or delete', function (string $roleName, bool $isWriter) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        "SELECT has_table_privilege(?, 'scores.variable_scores', ?) AS allowed", [$roleName, $privilegeName]
    )->allowed;

    $canInsert = $privilege('INSERT');

    expect($canInsert)->toBe($isWriter);
    expect($privilege('UPDATE'))->toBeFalse();
    expect($privilege('DELETE'))->toBeFalse();
})->with([
    'laravel' => ['nv_app', true],
    'ingest' => ['nv_ingest', false],
    'signals' => ['nv_signals', false],
]);

test('a cannot_assess variable with a score is rejected', function () {
    $insertScoredCannotAssess = fn () => insertVariableScore(array_merge(FAILED_GATE_FIELDS, ['score' => 40]));

    expect($insertScoredCannotAssess)->toThrow(QueryException::class, 'variable_scores_score_matches_status_check');
});

test('a score without its confidence is rejected', function () {
    $insertWithoutConfidence = fn () => insertVariableScore(['confidence' => null]);

    expect($insertWithoutConfidence)->toThrow(QueryException::class, 'variable_scores_score_matches_status_check');
});

test('a score without its coverage is rejected', function () {
    $insertWithoutCoverage = fn () => insertVariableScore(['coverage' => null]);

    expect($insertWithoutCoverage)->toThrow(QueryException::class, 'not-null');
});

test('a gate state that does not match the status is rejected', function (array $mismatchedFields) {
    $insertMismatch = fn () => insertVariableScore($mismatchedFields);

    expect($insertMismatch)->toThrow(QueryException::class, 'variable_scores_gate_matches_status_check');
})->with([
    'failed gate but scored' => [['gate_status' => 'failed', 'gate_failed_sub_id' => '1.1']],
    'unmeasured gate but scored' => [['gate_status' => 'unmeasured']],
    'provisional with a passed gate' => [['status' => 'provisional']],
    'failed gate without the failing sub-variable' => [array_merge(FAILED_GATE_FIELDS, ['gate_failed_sub_id' => null])],
    'V1 without a gate status' => [['gate_status' => null]],
    'V3 with a gate status' => [['variable_id' => 3]],
    'V3 marked provisional' => [array_merge(UNGATED_VARIABLE_FIELDS, ['status' => 'provisional'])],
]);

test('values outside their allowed range are rejected', function (array $outOfRange) {
    $insertOutOfRange = fn () => insertVariableScore($outOfRange);

    expect($insertOutOfRange)->toThrow(QueryException::class, 'variable_scores_allowed_values_check');
})->with([
    'variable 6' => [['variable_id' => 6]],
    'score above 100' => [['score' => 101]],
    'coverage above 1' => [['coverage' => 1.5]],
    'weight version 0' => [['weight_version' => 0]],
    'unknown status' => [['status' => 'unknown']],
]);
