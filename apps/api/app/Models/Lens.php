<?php

// A customer view of the five variables (e.g. the county planner lens): variable weights,
// thresholds and direction, as versioned JSON. Laravel writes lens.lenses (ADR-004a).

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One lens version (Bible §6.6 and §10, lens.lenses). The database rejects a config with
 * sub-variable weights. HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the
 * insert (ADR-012 §2).
 */
class Lens extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'lens.lenses';

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
            'config' => 'array',
            'version' => 'integer',
            'active' => 'boolean',
        ];
    }
}
