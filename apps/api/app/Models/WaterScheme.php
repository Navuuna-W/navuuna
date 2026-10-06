<?php

// What a water scheme register declares about one scheme: yield, production, status, operator.
// Python ingest writes records.water_schemes (ADR-004a); Laravel only reads it, e.g. for findings.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One aligned water scheme record (Bible §10, records.water_schemes). Each row points at the
 * document and text block it was read from.
 */
class WaterScheme extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'records.water_schemes';

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
            'rated_yield_m3d' => 'float',
            'reported_production_m3d' => 'float',
            'record_date' => 'date',
            'alignment_confidence' => 'float',
        ];
    }
}
