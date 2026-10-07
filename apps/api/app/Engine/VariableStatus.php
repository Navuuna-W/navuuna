<?php

// The status of one variable score, stored in scores.variable_scores.status. The UI shows it as a
// badge beside the score (Bible §6.3: provisional is never shown silently).

declare(strict_types=1);

namespace App\Engine;

/**
 * Whether a variable has a score, and how far to trust it. Implements Bible §6.3.
 */
enum VariableStatus: string
{
    /** Gate passed (or no gate) and at least one contributor measured. */
    case Scored = 'scored';

    /** No score: the gate failed, or no contributor is measured. */
    case CannotAssess = 'cannot_assess';

    /** Gate not measured: a score from what is there, at half confidence. */
    case Provisional = 'provisional';
}
