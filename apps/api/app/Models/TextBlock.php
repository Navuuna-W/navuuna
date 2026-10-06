<?php

// One block of text a parser cut out of a document, with its page and position.
// Python ingest writes raw.text_blocks (ADR-004a); Laravel only reads it, e.g. for evidence packs.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One text block (Bible §10, raw.text_blocks). `bbox` is a PostgreSQL double precision[]
 * and arrives as its text form, e.g. "{72,100.5,300,120}".
 */
class TextBlock extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'raw.text_blocks';

    /** The table has no created_at / updated_at columns. */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'block_index' => 'integer',
        ];
    }
}
