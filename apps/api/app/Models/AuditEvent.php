<?php

// One audited action: who did what to which row, with the row before and after.
// Laravel appends audit.events rows (ADR-004a); they are never edited.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One audit event (Bible §10, audit.events). A null user_id means the system acted.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 *
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 */
class AuditEvent extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'audit.events';

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
            'before' => 'array',
            'after' => 'array',
            'at' => 'datetime',
        ];
    }
}
