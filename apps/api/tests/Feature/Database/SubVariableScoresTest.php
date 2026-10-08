<?php

// Checks what the scores.sub_variable_scores migration promises: the database applies the same
// rules as the Python ScoreResult, so a measured row always has its evidence and an unmeasured row
// is never a 0. Only the signal service (nv_signals) may append rows, and nobody may change them.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert a registered adapter for sub-variable 2.4 and return its id.
 */
function insertStatusGapAdapter(): string
{
    DB::table('core.adapters')->insert([
        'module' => 'water', 'sub_id' => '2.4', 'version' => '1.0.0', 'signal_description' => 'Status gap',
        'code_ref' => 'modules/water/adapters/status_gap.py', 'entity_types' => '{point}',
    ]);

    return (string) DB::table('core.adapters')->where('sub_id', '2.4')->value('id');
}

/**
 * Insert a sub-variable score. By default a measured 2.4 score with one source.
 * The entity, source and adapter are only created when the caller did not pass them, so a test
 * running as nv_signals can create them first, as the owner, and pass them in.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertSubVariableScore(array $overrides = []): void
{
    $row = array_merge([
        'sub_id' => '2.4',
        'value' => '"not_working"',
        'score' => 100,
        'confidence' => 0.8,
        'status' => 'measured',
        'observed_at' => now(),
        'adapter_version' => '1.0.0',
    ], $overrides);
    $row['entity_id'] ??= insertCoreEntity();
    $row['source_ids'] ??= '{'.insertCoreSource().'}';
    $row['adapter_id'] ??= insertStatusGapAdapter();

    DB::table('scores.sub_variable_scores')->insert($row);
}

/** The fields an unmeasured row has: a reason, and no number. */
const NOT_MEASURED_FIELDS = [
    'status' => 'null_not_measured', 'value' => null, 'score' => null, 'confidence' => null,
    'observed_at' => null, 'source_ids' => '{}', 'null_reason' => 'no register record matched',
];

test('the signals role can append a measured score and an unmeasured one', function () {
    $sharedRows = [
        'entity_id' => insertCoreEntity(),
        'source_ids' => '{'.insertCoreSource().'}',
        'adapter_id' => insertStatusGapAdapter(),
    ];
    DB::statement('SET LOCAL ROLE nv_signals');

    insertSubVariableScore(array_merge($sharedRows, ['value' => '42.5', 'score' => 61]));
    insertSubVariableScore(array_merge($sharedRows, NOT_MEASURED_FIELDS));

    expect(DB::table('scores.sub_variable_scores')->count())->toBe(2);
});

test('only the signals role may insert, and no role may update or delete', function (string $roleName, bool $isWriter) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        "SELECT has_table_privilege(?, 'scores.sub_variable_scores', ?) AS allowed", [$roleName, $privilegeName]
    )->allowed;

    $canInsert = $privilege('INSERT');

    expect($canInsert)->toBe($isWriter);
    expect($privilege('UPDATE'))->toBeFalse();
    expect($privilege('DELETE'))->toBeFalse();
})->with([
    'signals' => ['nv_signals', true],
    'ingest' => ['nv_ingest', false],
    'laravel' => ['nv_app', false],
]);

test('a measured score is rejected when any of its evidence is missing', function (string $missingField, mixed $emptyValue) {
    $insertWithoutEvidence = fn () => insertSubVariableScore([$missingField => $emptyValue]);

    expect($insertWithoutEvidence)->toThrow(QueryException::class, 'sub_variable_scores_fields_match_status_check');
})->with([
    'value' => ['value', null],
    'score' => ['score', null],
    'confidence' => ['confidence', null],
    'observed_at' => ['observed_at', null],
    'source_ids' => ['source_ids', '{}'],
]);

test('a source list holding a NULL is rejected, even next to a real source', function (string $sourceIds) {
    $insertNullSource = fn () => insertSubVariableScore(['source_ids' => $sourceIds]);

    expect($insertNullSource)->toThrow(QueryException::class, 'sub_variable_scores_source_ids_no_null_check');
})->with([
    'only NULL' => ['{NULL}'],
    'NULL beside a real id' => ['{0192a000-0000-7000-8000-000000000001,NULL}'],
]);

test('a measured score with a null reason is rejected', function () {
    $insertMeasuredWithReason = fn () => insertSubVariableScore(['null_reason' => 'cloudy']);

    expect($insertMeasuredWithReason)->toThrow(QueryException::class, 'sub_variable_scores_fields_match_status_check');
});

test('an unmeasured score stored as 0 is rejected', function () {
    $insertZeroForMissing = fn () => insertSubVariableScore(array_merge(NOT_MEASURED_FIELDS, ['score' => 0]));

    expect($insertZeroForMissing)->toThrow(QueryException::class, 'sub_variable_scores_fields_match_status_check');
});

test('an unmeasured score with a blank reason is rejected', function () {
    $insertBlankReason = fn () => insertSubVariableScore(array_merge(NOT_MEASURED_FIELDS, ['null_reason' => '  ']));

    expect($insertBlankReason)->toThrow(QueryException::class, 'sub_variable_scores_fields_match_status_check');
});

test('a score above 100 or a confidence above 1 is rejected', function (array $outOfRange) {
    $insertOutOfRange = fn () => insertSubVariableScore($outOfRange);

    expect($insertOutOfRange)->toThrow(QueryException::class, 'sub_variable_scores_ranges_check');
})->with([
    'score' => [['score' => 101]],
    'confidence' => [['confidence' => 1.2]],
]);

test('a sub-variable id that is not in the 2.1 format is rejected', function () {
    $insertBadSubId = fn () => insertSubVariableScore(['sub_id' => 'water_status']);

    expect($insertBadSubId)->toThrow(QueryException::class, 'sub_variable_scores_sub_id_format_check');
});
