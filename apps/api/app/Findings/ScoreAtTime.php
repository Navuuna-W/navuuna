<?php

// One past score of a sub-variable and when it was computed. GapClosure reads a run of these,
// newest first, to decide whether a finding's gap has stayed closed (E8).

declare(strict_types=1);

namespace App\Findings;

use Carbon\CarbonImmutable;

/**
 * A scores.sub_variable_scores row reduced to what E8 needs. Score is null when not measured.
 */
final readonly class ScoreAtTime
{
    public function __construct(
        public ?float $score,
        public CarbonImmutable $computedAt,
    ) {}
}
