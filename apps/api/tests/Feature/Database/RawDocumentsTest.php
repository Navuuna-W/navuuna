<?php

// Checks what the raw.documents and raw.text_blocks migrations promise: the CHECKs reject bad
// rows, and only Python ingest (nv_ingest) can write either table (ADR-004a).

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the python ingest role can store a document and its text blocks', function () {
    $sourceId = insertCoreSource();
    DB::statement('SET LOCAL ROLE nv_ingest');

    $documentId = insertRawDocument(['source_id' => $sourceId]);
    insertRawTextBlock($documentId, ['bbox' => '{72, 100.5, 300, 120}']);

    expect(DB::table('raw.text_blocks')->where('document_id', $documentId)->count())->toBe(1);
});

test('only the python ingest role may insert or update raw document tables', function (string $tableName, string $roleName, bool $isWriter) {
    $canInsert = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'INSERT'])->allowed;
    $canUpdate = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'UPDATE'])->allowed;
    $canDelete = DB::selectOne('SELECT has_table_privilege(?, ?, ?) AS allowed', [$roleName, $tableName, 'DELETE'])->allowed;

    expect($canInsert)->toBe($isWriter);
    expect($canUpdate)->toBe($isWriter);
    expect($canDelete)->toBeFalse();
})->with(['raw.documents', 'raw.text_blocks'])->with([
    'ingest' => ['nv_ingest', true],
    'signals' => ['nv_signals', false],
    'laravel' => ['nv_app', false],
]);

test('a document whose md5 is not 32 hex characters is rejected', function () {
    $insertBadMd5 = fn () => insertRawDocument(['md5' => 'not-an-md5']);

    expect($insertBadMd5)->toThrow(QueryException::class, 'documents_md5_is_hex_check');
});

test('a document with an impossible http status is rejected', function () {
    $insertBadStatus = fn () => insertRawDocument(['http_status' => 700]);

    expect($insertBadStatus)->toThrow(QueryException::class, 'documents_http_status_check');
});

test('the same bytes from the same url are stored only once', function () {
    insertRawDocument(['url' => 'https://example.test/register.pdf', 'md5' => md5('register v1')]);

    $insertSameBytes = fn () => insertRawDocument(['url' => 'https://example.test/register.pdf', 'md5' => md5('register v1')]);

    expect($insertSameBytes)->toThrow(QueryException::class, 'documents_url_md5_unique');
});

test('a text block on page zero is rejected', function () {
    $documentId = insertRawDocument();

    $insertPageZero = fn () => insertRawTextBlock($documentId, ['page' => 0]);

    expect($insertPageZero)->toThrow(QueryException::class, 'text_blocks_position_check');
});

test('a text block whose bbox does not have four numbers is rejected', function () {
    $documentId = insertRawDocument();

    $insertShortBbox = fn () => insertRawTextBlock($documentId, ['bbox' => '{72, 100}']);

    expect($insertShortBbox)->toThrow(QueryException::class, 'text_blocks_bbox_has_four_numbers_check');
});
