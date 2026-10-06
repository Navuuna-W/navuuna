<?php

// Checks what the core.sources and core.entities migrations promise: the CHECKs reject wrong
// shapes, and only Python ingest (nv_ingest) can write entities (ADR-004a).

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('a point entity with a radius is stored with a version 7 id', function () {
    $entityId = insertCoreEntity();

    // Character 15 of a UUID is its version digit (RFC 9562).
    expect(substr($entityId, 14, 1))->toBe('7');
});

test('a point entity without a radius is rejected', function () {
    $insertPointWithoutRadius = fn () => insertCoreEntity(['radius_m' => null]);

    expect($insertPointWithoutRadius)->toThrow(QueryException::class, 'entities_radius_only_for_points_check');
});

test('a segment entity with a radius is rejected', function () {
    $insertSegmentWithRadius = fn () => insertCoreEntity([
        'entity_type' => 'segment',
        'geom' => makeGeometry('LINESTRING(36.82 -1.29, 36.83 -1.28)'),
        'radius_m' => 10,
    ]);

    expect($insertSegmentWithRadius)->toThrow(QueryException::class, 'entities_radius_only_for_points_check');
});

test('a segment entity with a polygon geometry is rejected', function () {
    $insertSegmentAsPolygon = fn () => insertCoreEntity([
        'entity_type' => 'segment',
        'geom' => makeGeometry(NAIROBI_SQUARE_WKT),
        'radius_m' => null,
    ]);

    expect($insertSegmentAsPolygon)->toThrow(QueryException::class, 'entities_geometry_matches_type_check');
});

test('an area entity accepts a multipolygon geometry', function () {
    $entityId = insertCoreEntity([
        'entity_type' => 'area',
        'geom' => makeGeometry('MULTIPOLYGON(((36.82 -1.29, 36.83 -1.29, 36.83 -1.28, 36.82 -1.29)))'),
        'radius_m' => null,
    ]);

    expect($entityId)->not->toBeEmpty();
});

test('an entity stored in a srid other than 4326 is rejected', function () {
    $insertInUtm = fn () => insertCoreEntity(['geom' => makeGeometry(NAIROBI_POINT_UTM_WKT, 32737)]);

    expect($insertInUtm)->toThrow(QueryException::class, 'SRID');
});

test('two entities with the same external ref are rejected', function () {
    insertCoreEntity(['external_ref' => 'osm-node-1']);

    $insertDuplicate = fn () => insertCoreEntity(['external_ref' => 'osm-node-1']);

    expect($insertDuplicate)->toThrow(QueryException::class, 'entities_external_ref_unique');
});

test('the python ingest role can write entities', function () {
    DB::statement('SET LOCAL ROLE nv_ingest');

    insertCoreEntity();

    expect(DB::table('core.entities')->count())->toBe(1);
});

test('the laravel and signals roles cannot write entities', function (string $roleName) {
    DB::statement("SET LOCAL ROLE {$roleName}");

    $insertEntity = fn () => insertCoreEntity();

    expect($insertEntity)->toThrow(QueryException::class, 'permission denied');
})->with(['nv_app', 'nv_signals']);

test('no runtime role can delete an entity', function (string $roleName) {
    insertCoreEntity();
    DB::statement("SET LOCAL ROLE {$roleName}");

    $deleteEntities = fn () => DB::table('core.entities')->delete();

    expect($deleteEntities)->toThrow(QueryException::class, 'permission denied');
})->with(['nv_ingest', 'nv_signals', 'nv_app']);

test('a source of an unknown kind is rejected', function () {
    $insertUnknownKind = fn () => DB::table('core.sources')->insert([
        'name' => 'Test source', 'kind' => 'rumour', 'licence' => 'CC-BY-4.0', 'attribution' => 'Test',
    ]);

    expect($insertUnknownKind)->toThrow(QueryException::class, 'sources_kind_check');
});
