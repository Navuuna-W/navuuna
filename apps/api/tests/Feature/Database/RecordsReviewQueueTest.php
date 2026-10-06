<?php

// Checks what the records.review_queue migration promises: entries name a records table, decisions
// always name a reviewer, and only Python ingest (nv_ingest) can write the queue (ADR-004a).

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert a review queue entry for a water scheme candidate.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertReviewQueueItem(array $overrides = []): void
{
    DB::table('records.review_queue')->insert(array_merge([
        'target_table' => 'records.water_schemes',
        'candidate' => '{"name": "Unclear scheme"}',
        'alignment_confidence' => 0.4,
        'reason' => 'confidence below 0.6',
    ], $overrides));
}

test('the python ingest role can add and decide a review queue entry', function () {
    DB::statement('SET LOCAL ROLE nv_ingest');

    insertReviewQueueItem();
    DB::table('records.review_queue')->update(['reviewed_by' => 'devyan', 'decision' => 'rejected']);

    expect(DB::table('records.review_queue')->value('decision'))->toBe('rejected');
});

test('only the python ingest role may insert or update the review queue', function (string $roleName, bool $isWriter) {
    $canInsert = DB::selectOne("SELECT has_table_privilege(?, 'records.review_queue', 'INSERT') AS allowed", [$roleName])->allowed;
    $canUpdate = DB::selectOne("SELECT has_table_privilege(?, 'records.review_queue', 'UPDATE') AS allowed", [$roleName])->allowed;
    $canDelete = DB::selectOne("SELECT has_table_privilege(?, 'records.review_queue', 'DELETE') AS allowed", [$roleName])->allowed;

    expect($canInsert)->toBe($isWriter);
    expect($canUpdate)->toBe($isWriter);
    expect($canDelete)->toBeFalse();
})->with([
    'ingest' => ['nv_ingest', true],
    'signals' => ['nv_signals', false],
    'laravel' => ['nv_app', false],
]);

test('a review entry for any table other than the two records tables is rejected', function () {
    $insertWrongTarget = fn () => insertReviewQueueItem(['target_table' => 'core.entities']);

    expect($insertWrongTarget)->toThrow(QueryException::class, 'review_queue_target_table_check');
});

test('a review decision without a reviewer is rejected', function () {
    $insertAnonymousDecision = fn () => insertReviewQueueItem(['decision' => 'approved']);

    expect($insertAnonymousDecision)->toThrow(QueryException::class, 'review_queue_decision_check');
});

test('a decision other than approved or rejected is rejected', function () {
    $insertUnknownDecision = fn () => insertReviewQueueItem(['reviewed_by' => 'devyan', 'decision' => 'maybe']);

    expect($insertUnknownDecision)->toThrow(QueryException::class, 'review_queue_decision_check');
});
