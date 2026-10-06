<?php

// An aligned row the aligner was unsure about, waiting for a person to approve or reject it.
// Python ingest and its review CLI write records.review_queue (ADR-004a); Laravel only reads it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One review queue entry (Bible §10, records.review_queue).
 */
class ReviewQueueItem extends Model
{
    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'records.review_queue';

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
            'candidate' => 'array',
            'alignment_confidence' => 'float',
        ];
    }
}
