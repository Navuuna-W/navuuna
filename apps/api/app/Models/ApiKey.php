<?php

// One API key, belonging to one api_client user. Only the key's SHA-256 is stored.
// Framework table in `public` (ADR-004a §1); only Laravel writes it.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An API key (NFR-05). HasUuids makes the UUIDv7 in PHP so Laravel knows the ID before the
 * insert (ADR-012 §2).
 *
 * @property string $user_id
 * @property string $name
 * @property string $key_hash
 * @property User $user
 */
#[Fillable(['user_id', 'name', 'key_hash'])]
class ApiKey extends Model
{
    use HasUuids;

    /** Schema-qualified, never left to search_path (Bible §14.12). */
    protected $table = 'public.api_keys';

    /**
     * The api_client user this key signs in as.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The value stored in key_hash for a plain key. Keys are long and random, so a fast
     * SHA-256 is safe and lets the guard look a key up directly (no bcrypt needed).
     */
    public static function hashKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }
}
