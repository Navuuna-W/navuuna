<?php

// Checks what the flags.flags and flags.transitions migrations promise: only V2 gaps become
// findings, an entity has at most one open finding per sub-variable, every transition has a note,
// and only Laravel (nv_app) may write — transitions append-only.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert a transition for the given finding.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertTransition(string $flagId, array $overrides = []): void
{
    DB::table('flags.transitions')->insert(array_merge([
        'flag_id' => $flagId,
        'from_state' => 'detected',
        'to_state' => 'held',
        'note' => 'Held automatically on detection',
    ], $overrides));
}

test('the laravel role can raise a finding, move it and record the transition', function () {
    $entityId = insertCoreEntity();
    DB::statement('SET LOCAL ROLE nv_app');

    $flagId = insertFinding(['entity_id' => $entityId]);
    insertTransition($flagId);
    DB::table('flags.flags')->where('id', $flagId)->update(['state' => 'explanation_checked']);

    expect(DB::table('flags.flags')->value('state'))->toBe('explanation_checked');
    expect(DB::table('flags.transitions')->count())->toBe(1);
});

test('only the laravel role may write findings, and transitions are append-only', function (string $tableName, string $roleName, bool $canInsert, bool $canUpdate) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        'SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, $privilegeName]
    )->allowed;

    $isInsertAllowed = $privilege('INSERT');

    expect($isInsertAllowed)->toBe($canInsert);
    expect($privilege('UPDATE'))->toBe($canUpdate);
    expect($privilege('DELETE'))->toBeFalse();
})->with([
    'flags, laravel' => ['flags.flags', 'nv_app', true, true],
    'flags, ingest' => ['flags.flags', 'nv_ingest', false, false],
    'flags, signals' => ['flags.flags', 'nv_signals', false, false],
    'transitions, laravel' => ['flags.transitions', 'nv_app', true, false],
    'transitions, ingest' => ['flags.transitions', 'nv_ingest', false, false],
    'transitions, signals' => ['flags.transitions', 'nv_signals', false, false],
]);

test('a second open finding for the same entity and sub-variable is rejected', function () {
    $entityId = insertCoreEntity();
    insertFinding(['entity_id' => $entityId]);

    $insertSecondOpen = fn () => insertFinding(['entity_id' => $entityId, 'state' => 'detected']);

    expect($insertSecondOpen)->toThrow(QueryException::class, 'flags_one_open_per_entity_sub_index');
});

test('a new finding may follow a dismissed one for the same entity and sub-variable', function () {
    $entityId = insertCoreEntity();
    insertFinding(['entity_id' => $entityId, 'state' => 'dismissed']);

    insertFinding(['entity_id' => $entityId]);

    expect(DB::table('flags.flags')->where('entity_id', $entityId)->count())->toBe(2);
});

test('a finding outside V2 or with an unknown severity or state is rejected', function (array $badFields) {
    $insertBadFinding = fn () => insertFinding($badFields);

    expect($insertBadFinding)->toThrow(QueryException::class, 'flags_allowed_values_check');
})->with([
    'V1 sub-variable' => [['sub_id' => '1.2']],
    'guard 2.6 is not a finding' => [['sub_id' => '2.6']],
    'unknown severity' => [['severity' => 'critical']],
    'unknown state' => [['state' => 'approved']],
]);

test('a transition without a real note is rejected', function () {
    $flagId = insertFinding();

    $insertBlankNote = fn () => insertTransition($flagId, ['note' => '   ']);

    expect($insertBlankNote)->toThrow(QueryException::class, 'transitions_note_not_blank_check');
});

test('a transition to the state it came from is rejected', function () {
    $flagId = insertFinding();

    $insertNoChange = fn () => insertTransition($flagId, ['from_state' => 'held', 'to_state' => 'held']);

    expect($insertNoChange)->toThrow(QueryException::class, 'transitions_states_check');
});
