<?php

// What turned out to be true about a published finding, recorded later by a person.
// The outcomes command (K-17) writes flags.outcomes (ADR-004a).

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One finding outcome (Bible §10, flags.outcomes): confirmed, refuted, partial or unknown.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 */
class FlagOutcome extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'flags.outcomes';

    /** The table has no created_at / updated_at columns; `at` plays that role. */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'at' => 'datetime',
        ];
    }
}
