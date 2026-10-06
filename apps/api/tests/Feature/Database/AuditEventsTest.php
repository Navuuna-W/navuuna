<?php

// Checks what the audit.events migration promises: every event names an action and a target,
// and only Laravel (nv_app) may append events, which nobody may change.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert an audit event about a finding moving to published.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertAuditEvent(array $overrides = []): void
{
    DB::table('audit.events')->insert(array_merge([
        'action' => 'flag_transition',
        'target_table' => 'flags.flags',
        'target_id' => DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id,
        'after' => '{"state": "published"}',
    ], $overrides));
}

test('the laravel role can append an audit event', function () {
    DB::statement('SET LOCAL ROLE nv_app');

    insertAuditEvent();

    expect(DB::table('audit.events')->count())->toBe(1);
});

test('only the laravel role may insert, and no role may update or delete', function (string $roleName, bool $isWriter) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        "SELECT has_table_privilege(?, 'audit.events', ?) AS allowed", [$roleName, $privilegeName]
    )->allowed;

    $canInsert = $privilege('INSERT');

    expect($canInsert)->toBe($isWriter);
    expect($privilege('UPDATE'))->toBeFalse();
    expect($privilege('DELETE'))->toBeFalse();
})->with([
    'laravel' => ['nv_app', true],
    'ingest' => ['nv_ingest', false],
    'signals' => ['nv_signals', false],
]);

test('an audit event without an action or target table is rejected', function (array $blankField) {
    $insertBlank = fn () => insertAuditEvent($blankField);

    expect($insertBlank)->toThrow(QueryException::class, 'events_action_not_blank_check');
})->with([
    'action' => [['action' => ' ']],
    'target table' => [['target_table' => '']],
]);
