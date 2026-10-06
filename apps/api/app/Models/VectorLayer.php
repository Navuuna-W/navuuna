<?php

// One vector layer a loader brought in (e.g. OSM water points), and where its file sits.
// Python ingest writes raw.vectors (ADR-004a); Laravel only reads it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One loaded vector layer (Bible §10, raw.vectors). Named VectorLayer, not Vector, because
 * each row describes a whole layer file, not one shape.
 */
class VectorLayer extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'raw.vectors';

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
            'feature_count' => 'integer',
            'loaded_at' => 'datetime',
        ];
    }
}
