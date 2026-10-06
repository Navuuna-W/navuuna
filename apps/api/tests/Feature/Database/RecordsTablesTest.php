<?php

// Checks what the records.water_schemes and records.road_contracts migrations promise: the CHECKs
// reject impossible declared values, each record points at the text block it came from, and only
// Python ingest (nv_ingest) can write them (ADR-004a).

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert a water scheme record read from a new document, matched to no entity yet.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertWaterSchemeRecord(array $overrides = []): void
{
    $documentId = insertRawDocument();

    DB::table('records.water_schemes')->insert(array_merge([
        'name' => 'Kibera Water Scheme',
        'rated_yield_m3d' => 500,
        'status_declared' => 'operational',
        'document_id' => $documentId,
        'block_id' => insertRawTextBlock($documentId),
        'aligner_version' => '0.1.0',
        'alignment_confidence' => 0.9,
    ], $overrides));
}

/**
 * Insert a road contract record read from a new document.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertRoadContractRecord(array $overrides = []): void
{
    $documentId = insertRawDocument();

    DB::table('records.road_contracts')->insert(array_merge([
        'contractor' => 'Test Contractors Ltd',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'pct_complete_declared' => 40,
        'document_id' => $documentId,
        'block_id' => insertRawTextBlock($documentId),
        'aligner_version' => '0.1.0',
        'alignment_confidence' => 0.8,
    ], $overrides));
}

test('the python ingest role can store water scheme and road contract records', function () {
    DB::statement('SET LOCAL ROLE nv_ingest');

    insertWaterSchemeRecord(['reported_production_m3d' => 350]);
    insertRoadContractRecord();

    expect(DB::table('records.water_schemes')->value('reported_production_m3d'))->toEqual(350);
    expect(DB::table('records.road_contracts')->count())->toBe(1);
});

test('only the python ingest role may insert or update water scheme and road contract records', function (string $tableName, string $roleName, bool $isWriter) {
    $canInsert = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'INSERT'])->allowed;
    $canUpdate = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'UPDATE'])->allowed;
    $canDelete = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'DELETE'])->allowed;

    expect($canInsert)->toBe($isWriter);
    expect($canUpdate)->toBe($isWriter);
    expect($canDelete)->toBeFalse();
})->with(['records.water_schemes', 'records.road_contracts'])->with([
    'ingest' => ['nv_ingest', true],
    'signals' => ['nv_signals', false],
    'laravel' => ['nv_app', false],
]);

test('a record without the text block it came from is rejected', function () {
    $insertWithoutBlock = fn () => insertWaterSchemeRecord(['block_id' => null]);

    expect($insertWithoutBlock)->toThrow(QueryException::class, 'not-null');
});

test('a record with alignment confidence above 1 is rejected', function () {
    $insertOverconfident = fn () => insertWaterSchemeRecord(['alignment_confidence' => 1.5]);

    expect($insertOverconfident)->toThrow(QueryException::class, 'water_schemes_alignment_confidence_check');
});

test('a water scheme with a negative yield is rejected', function () {
    $insertNegativeYield = fn () => insertWaterSchemeRecord(['rated_yield_m3d' => -10]);

    expect($insertNegativeYield)->toThrow(QueryException::class, 'water_schemes_volumes_not_negative_check');
});

test('a road contract that ends before it starts is rejected', function () {
    $insertBackwardsDates = fn () => insertRoadContractRecord(['start_date' => '2025-06-01', 'end_date' => '2025-01-01']);

    expect($insertBackwardsDates)->toThrow(QueryException::class, 'road_contracts_declared_values_check');
});

test('a road contract more than 100 percent complete is rejected', function () {
    $insertOverComplete = fn () => insertRoadContractRecord(['pct_complete_declared' => 140]);

    expect($insertOverComplete)->toThrow(QueryException::class, 'road_contracts_declared_values_check');
});
