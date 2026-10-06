<?php

// Checks what the flags.evidence_packs and flags.outcomes migrations promise: a finding's pack
// always points at real evidence, a finding has one pack, outcomes use the four allowed values,
// and only Laravel (nv_app) may write either table.

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert an evidence pack for the given finding, resting on one register record.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertEvidencePack(string $flagId, array $overrides = []): void
{
    DB::table('flags.evidence_packs')->insert(array_merge([
        'flag_id' => $flagId,
        'record_ids' => '{'.DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id.'}',
        'narrative' => 'The register lists this water point as operational; residents reported it not working.',
        'adapter_version' => '1.0.0',
    ], $overrides));
}

/**
 * Insert an outcome for the given finding, recorded by a new analyst.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertFlagOutcome(string $flagId, array $overrides = []): void
{
    $analystEmail = 'analyst-'.uniqid().'@example.test';
    DB::table('public.users')->insert(['name' => 'Test Analyst', 'email' => $analystEmail, 'password' => 'x']);

    DB::table('flags.outcomes')->insert(array_merge([
        'flag_id' => $flagId,
        'outcome' => 'confirmed',
        'evidence' => 'Site visit: pump broken since August',
        'recorded_by' => DB::table('public.users')->where('email', $analystEmail)->value('id'),
    ], $overrides));
}

test('the laravel role can store an evidence pack and an outcome', function () {
    $entityId = insertCoreEntity();
    DB::statement('SET LOCAL ROLE nv_app');

    $flagId = insertFinding(['entity_id' => $entityId]);
    insertEvidencePack($flagId);
    insertFlagOutcome($flagId);

    expect(DB::table('flags.evidence_packs')->count())->toBe(1);
    expect(DB::table('flags.outcomes')->value('outcome'))->toBe('confirmed');
});

test('only the laravel role may write evidence packs and outcomes', function (string $tableName, string $roleName, bool $isWriter) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        'SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, $privilegeName]
    )->allowed;

    $canInsert = $privilege('INSERT');

    expect($canInsert)->toBe($isWriter);
    expect($privilege('UPDATE'))->toBe($isWriter);
    expect($privilege('DELETE'))->toBeFalse();
})->with(['flags.evidence_packs', 'flags.outcomes'])->with([
    'laravel' => ['nv_app', true],
    'ingest' => ['nv_ingest', false],
    'signals' => ['nv_signals', false],
]);

test('an evidence pack that points at no evidence is rejected', function () {
    $flagId = insertFinding();

    $insertEmptyPack = fn () => insertEvidencePack($flagId, ['record_ids' => '{}']);

    expect($insertEmptyPack)->toThrow(QueryException::class, 'evidence_packs_has_evidence_check');
});

test('an evidence pack without a narrative is rejected', function () {
    $flagId = insertFinding();

    $insertWithoutNarrative = fn () => insertEvidencePack($flagId, ['narrative' => ' ']);

    expect($insertWithoutNarrative)->toThrow(QueryException::class, 'evidence_packs_has_evidence_check');
});

test('a second evidence pack for the same finding is rejected', function () {
    $flagId = insertFinding();
    insertEvidencePack($flagId);

    $insertSecondPack = fn () => insertEvidencePack($flagId);

    expect($insertSecondPack)->toThrow(QueryException::class, 'evidence_packs_flag_id_unique');
});

test('an outcome outside the four allowed values is rejected', function () {
    $flagId = insertFinding();

    $insertUnknownOutcome = fn () => insertFlagOutcome($flagId, ['outcome' => 'probably']);

    expect($insertUnknownOutcome)->toThrow(QueryException::class, 'outcomes_allowed_values_check');
});

test('an outcome without evidence is rejected', function () {
    $flagId = insertFinding();

    $insertWithoutEvidence = fn () => insertFlagOutcome($flagId, ['evidence' => '']);

    expect($insertWithoutEvidence)->toThrow(QueryException::class, 'outcomes_allowed_values_check');
});
