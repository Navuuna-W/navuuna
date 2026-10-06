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

test('all migrations roll back to an empty database and run up again', function () {
    Artisan::call('migrate:fresh');

    $resetExitCode = Artisan::call('migrate:reset');
    $tablesAfterReset = countFrameworkTables();
    $migrateExitCode = Artisan::call('migrate');

    expect($resetExitCode)->toBe(0);
    expect($migrateExitCode)->toBe(0);
    expect($tablesAfterReset)->toBe(0);
    expect(countFrameworkTables())->toBeGreaterThan(0);
});
