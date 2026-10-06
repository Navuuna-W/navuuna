<?php

// A place data comes from: a scraped site, a raster, a vector layer, an upload or the community.
// Python ingest writes core.sources (ADR-004a); Laravel only reads it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One data source with its licence and attribution (Bible §10, core.sources).
 */
class Source extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'core.sources';

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
            'active' => 'boolean',
        ];
    }
}
