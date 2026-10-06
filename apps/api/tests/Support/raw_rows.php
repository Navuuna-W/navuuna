<?php

// Helpers that insert raw.* rows with plain SQL, the way Python ingest will write them.
// Shared by the database tests; loaded once from tests/Pest.php.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Insert a fetched PDF and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertRawDocument(array $overrides = []): string
{
    $row = array_merge([
        'source_id' => insertCoreSource(),
        'url' => 'https://example.test/register-'.uniqid().'.pdf',
        'md5' => md5(uniqid()),
        'storage_path' => 'documents/test.pdf',
        'mime' => 'application/pdf',
        'http_status' => 200,
    ], $overrides);

    DB::table('raw.documents')->insert($row);

    return (string) DB::table('raw.documents')->where('url', $row['url'])->value('id');
}

/**
 * Insert a text block on page 1 of the given document and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertRawTextBlock(string $documentId, array $overrides = []): string
{
    $row = array_merge([
        'document_id' => $documentId,
        'page' => 1,
        'block_index' => 0,
        'text' => 'Kibera Water Scheme  500 m3/day  Operational',
    ], $overrides);

    DB::table('raw.text_blocks')->insert($row);

    return (string) DB::table('raw.text_blocks')
        ->where(['document_id' => $documentId, 'page' => $row['page'], 'block_index' => $row['block_index']])
        ->value('id');
}
