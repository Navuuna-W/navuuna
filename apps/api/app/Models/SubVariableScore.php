<?php

// One adapter answer for one entity and one sub-variable: measured with its evidence, or
// null_not_measured with a reason. The Python runner writes scores.sub_variable_scores
// (ADR-004a); Laravel's rollup only reads it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One sub-variable score (Bible §10, scores.sub_variable_scores). Rows are never updated.
 * `source_ids` is a PostgreSQL uuid[] and arrives as its text form, e.g. "{0190…,0190…}".
 */
class SubVariableScore extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'scores.sub_variable_scores';

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
            'value' => 'json',
            'score' => 'float',
            'confidence' => 'float',
            'observed_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }
}
