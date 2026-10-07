<?php

// Checks what the first three migrations promise: seven domain schemas, a UUIDv7 default
// that really makes version-7 IDs, and runtime roles that can only reach what ADR-004a allows.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** How far a fresh UUIDv7 timestamp may be from the database clock, in milliseconds. */
const ALLOWED_CLOCK_DRIFT_MILLISECONDS = 1000;

test('the database has the seven domain schemas', function () {
    $expectedSchemas = ['audit', 'core', 'flags', 'lens', 'raw', 'records', 'scores'];

    $schemaRows = DB::select(
        'SELECT nspname FROM pg_namespace WHERE nspname = ANY(?) ORDER BY nspname',
        ['{'.implode(',', $expectedSchemas).'}']
    );

    expect(array_column($schemaRows, 'nspname'))->toBe($expectedSchemas);
});

test('postgis is installed in the public schema', function () {
    $extensionRow = DB::selectOne(
        "SELECT n.nspname AS schema_name FROM pg_extension e
         JOIN pg_namespace n ON n.oid = e.extnamespace WHERE e.extname = 'postgis'"
    );

    expect($extensionRow?->schema_name)->toBe('public');
});

test('the uuid default generates version 7 ids with the current time', function () {
    // Compare with the database's clock, not PHP's: the two machines' clocks can differ.
    $row = DB::selectOne(
        'SELECT public.uuid_generate_v7()::text AS id,
                floor(extract(epoch FROM clock_timestamp()) * 1000)::bigint AS database_milliseconds'
    );

    $generatedId = $row->id;

    // Layout: tttttttt-tttt-7xxx-Vxxx-xxxxxxxxxxxx, where V is 8, 9, a or b (RFC 9562).
    expect($generatedId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    $timestampHex = str_replace('-', '', substr($generatedId, 0, 13));
    expect(abs(hexdec($timestampHex) - $row->database_milliseconds))->toBeLessThan(ALLOWED_CLOCK_DRIFT_MILLISECONDS);
});

test('a user inserted without an id gets a version 7 id from the database', function () {
    DB::table('public.users')->insert(['name' => 'Test Analyst', 'email' => 'analyst@example.test', 'password' => 'x', 'role' => 'analyst']);

    $storedId = DB::table('public.users')->where('email', 'analyst@example.test')->value('id');

    expect(substr((string) $storedId, 14, 1))->toBe('7');
});

test('the three runtime roles exist and cannot log in on their own', function () {
    $roleRows = DB::select(
        "SELECT rolname, rolcanlogin FROM pg_roles WHERE rolname IN ('nv_ingest', 'nv_signals', 'nv_app') ORDER BY rolname"
    );

    expect(array_column($roleRows, 'rolname'))->toBe(['nv_app', 'nv_ingest', 'nv_signals']);
    expect(array_column($roleRows, 'rolcanlogin'))->each->toBeFalse();
});

test('the python ingest role cannot read the users table', function () {
    DB::statement('SET LOCAL ROLE nv_ingest');

    $readUsers = fn () => DB::select('SELECT id FROM public.users');

    expect($readUsers)->toThrow(QueryException::class, 'permission denied');
});

test('the laravel role can read and write the users table', function () {
    DB::statement('SET LOCAL ROLE nv_app');

    DB::table('public.users')->insert(['name' => 'Test Reviewer', 'email' => 'reviewer@example.test', 'password' => 'x', 'role' => 'analyst']);

    expect(DB::table('public.users')->count())->toBe(1);
});
