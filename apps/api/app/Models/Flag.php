<?php

// A finding: a gap between what a record declares and what is observed, for one entity and one
// V2 sub-variable. The findings engine (K-13) and FlagWorkflow (K-14) write flags.flags (ADR-004a).

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One finding (Bible §10, flags.flags). A held finding must never reach a non-analyst
 * (CLAUDE.md §4); the policies that enforce that come in K-11a and K-14.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 */
class Flag extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'flags.flags';

    /** The table has no created_at / updated_at columns; detected_at and state_changed_at play that role. */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'declared' => 'array',
            'observed' => 'array',
            'gap' => 'float',
            'detected_at' => 'datetime',
            'state_changed_at' => 'datetime',
        ];
    }
}
