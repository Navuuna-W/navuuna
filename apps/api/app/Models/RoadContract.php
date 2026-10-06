<?php

// What a road contract report declares: contractor, length, value, dates, percent complete.
// Python ingest writes records.road_contracts (ADR-004a); Laravel only reads it, e.g. for findings.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One aligned road contract record (Bible §10, records.road_contracts). Each row points at the
 * document and text block it was read from.
 */
class RoadContract extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'records.road_contracts';

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
            'length_km_declared' => 'float',
            'value_kes' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'pct_complete_declared' => 'float',
            'report_date' => 'date',
            'alignment_confidence' => 'float',
        ];
    }
}
