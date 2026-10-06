<?php

// The current lens score and map colour for one entity under one lens, with its coverage and
// confidence. Laravel's lens application writes lens.lens_scores (ADR-004a); tiles read it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One lens score (Bible §10, lens.lens_scores). One row per lens × entity, updated in place.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 */
class LensScore extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'lens.lens_scores';

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
            'score' => 'float',
            'coverage' => 'float',
            'confidence' => 'float',
            'computed_at' => 'datetime',
        ];
    }
}
