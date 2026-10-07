<?php

// The seven states a finding can be in (Bible §6.5, ADR-013). Stored in flags.flags.state;
// FlagWorkflow decides which moves between them are legal.

declare(strict_types=1);

namespace App\Findings;

use App\Models\Flag;

/**
 * A finding's place in its lifecycle. Implements Bible §6.5.
 */
enum FlagState: string
{
    /** Raised by the findings engine (K-13); moved to held straight away. */
    case Detected = 'detected';

    /** Waiting for an analyst. Never shown to anyone else (CLAUDE.md §4). */
    case Held = 'held';

    /** An analyst has checked for a legitimate explanation (the 2.6 guard, ADR-010 DEC-10). */
    case ExplanationChecked = 'explanation_checked';

    /** Public: shown in the Passport and the JSON API. */
    case Published = 'published';

    /** Closed without publishing: a legitimate explanation, or the gap closed (E8). */
    case Dismissed = 'dismissed';

    /** The named party disputes a published finding; hidden while it is looked at (ADR-013). */
    case Contested = 'contested';

    /** Closed after publishing: the contest was accepted, or the gap closed (E8). */
    case Resolved = 'resolved';

    /**
     * True for the states every role may see (Flag::PUBLIC_STATES, Bible §11).
     */
    public function isPublic(): bool
    {
        return in_array($this->value, Flag::PUBLIC_STATES, true);
    }
}
