<?php

// One change of a finding's state, with who made it, when and why.
// FlagWorkflow (K-14) appends flags.transitions rows (ADR-004a); they are never edited.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One finding state change (Bible §10, flags.transitions). A null user_id means the system.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 */
class FlagTransition extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'flags.transitions';

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
