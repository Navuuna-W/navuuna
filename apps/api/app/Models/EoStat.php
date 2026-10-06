<?php

// One satellite index value (NDWI, NDVI, NDBI) for one entity on one acquisition date.
// Python ingest writes raw.eo_stats (ADR-004a); Laravel only reads it, e.g. for evidence packs.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One earth-observation statistic (Bible §10, raw.eo_stats).
 */
class EoStat extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'raw.eo_stats';

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
            'value' => 'float',
            'cloud_pct' => 'float',
            'acquired_at' => 'datetime',
        ];
    }
}
