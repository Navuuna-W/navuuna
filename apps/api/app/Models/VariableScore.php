<?php

// The rollup's answer for one entity and one of the five variables: score, coverage, confidence,
// status and gate state. Laravel's rollup (K-10) writes scores.variable_scores (ADR-004a).

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One variable score (Bible §10, scores.variable_scores). Rows are never updated: a new rollup
 * appends a new row, and the newest per entity × variable is the current one.
 */
class VariableScore extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'scores.variable_scores';

    /** The table has no created_at / updated_at columns; computed_at plays that role. */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variable_id' => 'integer',
            'score' => 'float',
            'coverage' => 'float',
            'confidence' => 'float',
            'weight_version' => 'integer',
            'computed_at' => 'datetime',
            'inputs' => 'array',
        ];
    }
}
