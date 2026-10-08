<?php

// One finding RaiseRules has decided to raise. The findings engine turns it into a flags.flags
// row plus its evidence pack.

declare(strict_types=1);

namespace App\Findings;

use App\Engine\SubVariableInput;

/**
 * A gap big enough to raise, with its severity and the score that showed it.
 */
final readonly class FindingToRaise
{
    public function __construct(
        public string $subId,
        public Severity $severity,
        /** The newest score of the sub-variable; its source row leads to the evidence. */
        public SubVariableInput $score,
    ) {}
}
