<?php

// Helpers that insert core.* rows with plain SQL, the way the Python services will write them.
// Shared by the database tests; loaded once from tests/Pest.php.

declare(strict_types=1);

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/** A point in central Nairobi, as WKT. */
const NAIROBI_POINT_WKT = 'POINT(36.8219 -1.2921)';

/** A small square in central Nairobi, as WKT. */
const NAIROBI_SQUARE_WKT = 'POLYGON((36.82 -1.29, 36.83 -1.29, 36.83 -1.28, 36.82 -1.28, 36.82 -1.29))';

/** The same point in UTM 37S, which is for measuring, never for storage (Bible §10). */
const NAIROBI_POINT_UTM_WKT = 'POINT(257000 9857000)';

/**
 * Make a geometry value from WKT in the given SRID.
 */
function makeGeometry(string $wkt, int $srid = 4326): Expression
{
    return DB::raw("public.ST_GeomFromText('{$wkt}', {$srid})");
}

/**
 * Insert an entity and return its id. By default a water point with a 50 m radius.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertCoreEntity(array $overrides = []): string
{
    $row = array_merge([
        'entity_type' => 'point',
        'name' => 'Test water point',
        'external_ref' => 'test-'.uniqid(),
        'geom' => makeGeometry(NAIROBI_POINT_WKT),
        'radius_m' => 50,
    ], $overrides);

    DB::table('core.entities')->insert($row);

    return (string) DB::table('core.entities')->where('external_ref', $row['external_ref'])->value('id');
}

/**
 * Insert a community source and return its id.
 */
function insertCoreSource(): string
{
    $name = 'Test source '.uniqid();
    DB::table('core.sources')->insert([
        'name' => $name, 'kind' => 'community', 'licence' => 'CC-BY-4.0', 'attribution' => 'Test',
    ]);

    return (string) DB::table('core.sources')->where('name', $name)->value('id');
}
