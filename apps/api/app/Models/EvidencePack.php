<?php

// The evidence behind one finding: the rows it rests on and a plain-language narrative.
// The findings engine (K-13) writes flags.evidence_packs (ADR-004a).

declare(strict_types=1);

namespace App\Models;

use App\Models\Casts\UuidList;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One evidence pack (Bible §10, flags.evidence_packs). The *_ids columns are PostgreSQL uuid[];
 * UuidList reads them as PHP lists.
 * HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the insert (ADR-012 §2).
 *
 * @property list<string> $record_ids
 * @property list<string> $observation_ids
 * @property list<string> $document_ids
 * @property list<string> $eo_stat_ids
 * @property Carbon $generated_at
 */
class EvidencePack extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'flags.evidence_packs';

    /** The table has no created_at / updated_at columns; generated_at plays that role. */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'record_ids' => UuidList::class,
            'observation_ids' => UuidList::class,
            'document_ids' => UuidList::class,
            'eo_stat_ids' => UuidList::class,
        ];
    }
}
