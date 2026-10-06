<?php

// A registered adapter version: the code that scores one sub-variable for some entity types.
// The Python registry writes core.adapters (ADR-004a); Laravel only reads it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One adapter version (Bible §10, core.adapters). `entity_types` is a PostgreSQL text[]
 * and arrives as its text form, e.g. "{point,area}".
 */
class Adapter extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'core.adapters';

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
            'enabled' => 'boolean',
        ];
    }
}
