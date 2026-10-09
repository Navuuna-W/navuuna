<?php

// What turned out to be true about a published finding (FR-19). Stored in flags.outcomes.outcome;
// the table's CHECK allows exactly these four values. Written by `outcomes:record`.

declare(strict_types=1);

namespace App\Findings;

/**
 * The result of checking a published finding later. Implements FR-19 and Bible §10.
 */
enum Outcome: string
{
    /** The finding was right. */
    case Confirmed = 'confirmed';

    /** The finding was wrong. */
    case Refuted = 'refuted';

    /** Part of the finding was right. */
    case Partial = 'partial';

    /** Someone checked but could not tell. */
    case Unknown = 'unknown';

    /**
     * All four values, for error messages and the command's help text.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (Outcome $outcome) => $outcome->value, self::cases());
    }
}
