<?php

// A file a scraper fetched: its URL, MD5 and where the bytes sit in MinIO.
// Python ingest writes raw.documents (ADR-004a); Laravel only reads it, e.g. for evidence packs.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One fetched document (Bible §10, raw.documents).
 */
class Document extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'raw.documents';

    /** The table has no created_at / updated_at columns; fetched_at plays that role. */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }
}
