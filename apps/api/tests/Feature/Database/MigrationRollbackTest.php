<?php

// Every migration must have a real down() (CLAUDE.md §4). This test proves it end to end:
// roll everything back to an empty database, check nothing is left, then migrate up again.
// No RefreshDatabase here: migrate:reset cannot run inside the test's wrapping transaction.

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Count our tables in `public`. The migrations log and PostGIS's spatial_ref_sys are not
 * created by any migration's up(), so they are left out.
 */
function countFrameworkTables(): int
{
    return (int) DB::selectOne(
        "SELECT count(*) AS table_count FROM pg_tables
         WHERE schemaname = 'public' AND tablename NOT IN ('migrations', 'spatial_ref_sys')"
    )->table_count;
}

/**
 * Count the domain schemas that currently exist.
 */
function countDomainSchemas(): int
{
    return (int) DB::selectOne(
        "SELECT count(*) AS schema_count FROM pg_namespace
         WHERE nspname IN ('core', 'raw', 'records', 'scores', 'flags', 'lens', 'audit')"
    )->schema_count;
}

/**
 * True when public.uuid_generate_v7() exists.
 */
function isUuidFunctionPresent(): bool
{
    return DB::selectOne("SELECT to_regprocedure('public.uuid_generate_v7()') AS name")->name !== null;
}

test('all migrations roll back to an empty database and run up again', function () {
    Artisan::call('migrate:fresh');

    $resetExitCode = Artisan::call('migrate:reset');
    $tablesAfterReset = countFrameworkTables();
    $schemasAfterReset = countDomainSchemas();
    $isUuidFunctionLeftAfterReset = isUuidFunctionPresent();
    $migrateExitCode = Artisan::call('migrate');

    expect($resetExitCode)->toBe(0);
    expect($tablesAfterReset)->toBe(0);
    expect($schemasAfterReset)->toBe(0);
    expect($isUuidFunctionLeftAfterReset)->toBeFalse();
    expect($migrateExitCode)->toBe(0);
    expect(countDomainSchemas())->toBe(7);
    expect(isUuidFunctionPresent())->toBeTrue();
});
