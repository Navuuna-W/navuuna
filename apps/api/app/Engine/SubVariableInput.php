<?php

// One sub-variable score as the rollup sees it: the newest scores.sub_variable_scores row for an
// entity and sub-variable. The rollup turns a set of these into one variable score.

declare(strict_types=1);

namespace App\Engine;

/**
 * The newest score for one entity × sub-variable. Score and confidence are null when the
 * adapter could not measure it (null_not_measured — never 0, CLAUDE.md §4).
 */
final readonly class SubVariableInput
{
    public function __construct(
        public string $subId,
        public ?float $score,
        public ?float $confidence,
        /** The scores.sub_variable_scores row this came from, kept in variable_scores.inputs. */
        public string $sourceRowId,
    ) {}

    /**
     * True when the adapter measured it. A confidence of 0 adds nothing to a weighted average,
     * so it counts as not measured; that also keeps the rollup from dividing by zero.
     */
    public function isMeasured(): bool
    {
        return $this->score !== null && $this->confidence !== null && $this->confidence > 0;
    }
}
