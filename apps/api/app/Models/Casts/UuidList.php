<?php

// Reads a PostgreSQL uuid[] column, which arrives as text like "{0190…,0190…}", as a PHP list.
// Used by EvidencePack's *_ids columns. Read-only: the findings engine (K-13) writes them.

declare(strict_types=1);

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * uuid[] text → list<string>. UUIDs contain no commas or quotes, so splitting on commas is safe.
 *
 * @implements CastsAttributes<list<string>, never>
 */
class UuidList implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $insideBraces = trim((string) $value, '{}');
        if ($insideBraces === '') {
            return [];
        }

        return explode(',', $insideBraces);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): never
    {
        throw new LogicException("{$key} is written by the findings engine (K-13), not through the model.");
    }
}
