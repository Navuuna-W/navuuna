<?php

// Checks what the core.adapters, core.consents and core.observations migrations promise:
// the CHECKs reject bad rows, and each table has one writer role (ADR-004a).

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert an adapter for sub-variable 2.1. Entity types use PostgreSQL array text, e.g. "{point,area}".
 */
function insertCoreAdapter(string $entityTypes): void
{
    DB::table('core.adapters')->insert([
        'module' => 'water', 'sub_id' => '2.1', 'version' => '1.0.0', 'signal_description' => 'Test',
        'code_ref' => 'modules/water/adapters/test.py', 'entity_types' => $entityTypes,
    ]);
}

test('the signals role can write an adapter', function () {
    DB::statement('SET LOCAL ROLE nv_signals');

    insertCoreAdapter('{point}');

    expect(DB::table('core.adapters')->count())->toBe(1);
});

test('the python ingest role cannot write adapters', function () {
    DB::statement('SET LOCAL ROLE nv_ingest');

    $insertAdapter = fn () => insertCoreAdapter('{point}');

    expect($insertAdapter)->toThrow(QueryException::class, 'permission denied');
});

test('an adapter for an unknown entity type is rejected', function () {
    $insertUnknownType = fn () => insertCoreAdapter('{asset}');

    expect($insertUnknownType)->toThrow(QueryException::class, 'adapters_entity_types_check');
});

test('the laravel role can write a consent and an observation', function () {
    $entityId = insertCoreEntity();
    $sourceId = insertCoreSource();
    $contributorId = (string) DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id;
    DB::statement('SET LOCAL ROLE nv_app');

    DB::table('core.consents')->insert(['contributor_id' => $contributorId, 'scope' => 'observations', 'text_version' => 'v1']);
    $consentId = DB::table('core.consents')->value('id');
    DB::table('core.observations')->insert([
        'entity_id' => $entityId, 'source_id' => $sourceId, 'kind' => 'water_point_status',
        'payload' => '{"is_working": false}', 'observed_at' => now(),
        'contributor_id' => $contributorId, 'consent_id' => $consentId,
    ]);

    expect(DB::table('core.observations')->count())->toBe(1);
});

test('the python ingest role cannot write observations', function () {
    $entityId = insertCoreEntity();
    $sourceId = insertCoreSource();
    DB::statement('SET LOCAL ROLE nv_ingest');

    $insertObservation = fn () => DB::table('core.observations')->insert([
        'entity_id' => $entityId, 'source_id' => $sourceId, 'kind' => 'water_point_status',
        'payload' => '{}', 'observed_at' => now(),
    ]);

    expect($insertObservation)->toThrow(QueryException::class, 'permission denied');
});

test('a contributor observation without a consent is rejected', function () {
    $entityId = insertCoreEntity();
    $sourceId = insertCoreSource();

    $insertWithoutConsent = fn () => DB::table('core.observations')->insert([
        'entity_id' => $entityId, 'source_id' => $sourceId, 'kind' => 'water_point_status',
        'payload' => '{}', 'observed_at' => now(),
        'contributor_id' => DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id,
    ]);

    expect($insertWithoutConsent)->toThrow(QueryException::class, 'observations_contributor_has_consent_check');
});
