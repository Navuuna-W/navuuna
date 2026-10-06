<?php

// Helpers that insert flags.* rows with plain SQL. Shared by the database tests; loaded once
// from tests/Pest.php.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Insert a held 2.4 finding and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertFinding(array $overrides = []): string
{
    $row = array_merge([
        'sub_id' => '2.4',
        'severity' => 'high',
        'state' => 'held',
        'declared' => '{"status": "operational"}',
        'observed' => '{"status": "not_working"}',
    ], $overrides);
    $row['entity_id'] ??= insertCoreEntity();

    return (string) DB::table('flags.flags')->insertGetId($row);
}
