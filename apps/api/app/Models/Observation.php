<?php

// Something seen about one entity at one time, such as a community report on a water point.
// Laravel writes core.observations (ADR-004a); adapters read them as inputs.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One observation (Bible §10, core.observations). A contributor's observation always has a consent.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 */
class Observation extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'core.observations';

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
            'payload' => 'array',
            'observed_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }
}
