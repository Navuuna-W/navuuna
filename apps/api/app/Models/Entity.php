<?php

// A scored entity: a point, parcel, segment or area with a persistent identity (Bible §5).
// Python ingest writes core.entities (ADR-004a); Laravel only reads it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One scored entity (Bible §10, core.entities). `geom` is left as PostGIS returns it;
 * code that needs coordinates asks PostGIS for them (ST_AsGeoJSON) in its own query.
 */
class Entity extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'core.entities';

    /** The table has created_at but no updated_at. */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'radius_m' => 'float',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
