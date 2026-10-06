<?php

// Checks what the raw.eo_stats and raw.vectors migrations promise: the CHECKs reject bad rows,
// and only Python ingest (nv_ingest) can write either table (ADR-004a).

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert an NDWI reading for a new water point.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertRawEoStat(array $overrides = []): void
{
    DB::table('raw.eo_stats')->insert(array_merge([
        'source_id' => insertCoreSource(),
        'entity_id' => insertCoreEntity(),
        'product' => 'SENTINEL2_L2A',
        'index_name' => 'NDWI',
        'value' => 0.31,
        'acquired_at' => now(),
        'cloud_pct' => 4.5,
    ], $overrides));
}

/**
 * Insert a loaded vector layer.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertRawVectorLayer(array $overrides = []): void
{
    DB::table('raw.vectors')->insert(array_merge([
        'source_id' => insertCoreSource(),
        'layer' => 'osm_water_points',
        'feature_count' => 1200,
        'storage_path' => 'vectors/osm_water_points.gpkg',
    ], $overrides));
}

test('the python ingest role can store an eo statistic and a vector layer', function () {
    DB::statement('SET LOCAL ROLE nv_ingest');

    insertRawEoStat();
    insertRawVectorLayer();

    expect(DB::table('raw.eo_stats')->count())->toBe(1);
    expect(DB::table('raw.vectors')->count())->toBe(1);
});

test('only the python ingest role may insert or update eo stats and vectors', function (string $tableName, string $roleName, bool $isWriter) {
    $canInsert = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'INSERT'])->allowed;
    $canUpdate = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'UPDATE'])->allowed;
    $canDelete = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'DELETE'])->allowed;

    expect($canInsert)->toBe($isWriter);
    expect($canUpdate)->toBe($isWriter);
    expect($canDelete)->toBeFalse();
})->with(['raw.eo_stats', 'raw.vectors'])->with([
    'ingest' => ['nv_ingest', true],
    'signals' => ['nv_signals', false],
    'laravel' => ['nv_app', false],
]);

test('an eo statistic without a value is rejected', function () {
    $insertWithoutValue = fn () => insertRawEoStat(['value' => null]);

    expect($insertWithoutValue)->toThrow(QueryException::class, 'not-null');
});

test('an eo statistic with cloud cover above 100 percent is rejected', function () {
    $insertTooCloudy = fn () => insertRawEoStat(['cloud_pct' => 120]);

    expect($insertTooCloudy)->toThrow(QueryException::class, 'eo_stats_cloud_pct_check');
});

test('a vector layer with a negative feature count is rejected', function () {
    $insertNegativeCount = fn () => insertRawVectorLayer(['feature_count' => -1]);

    expect($insertNegativeCount)->toThrow(QueryException::class, 'vectors_feature_count_check');
});
