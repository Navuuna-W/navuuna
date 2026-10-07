<?php

// What VariableRollup works out for one entity × variable, before it is saved. RollupEntity (K-10b)
// writes it as one scores.variable_scores row.

declare(strict_types=1);

namespace App\Engine;

/**
 * One variable's rolled-up score with its coverage and confidence beside it (CLAUDE.md §4).
 */
final readonly class VariableResult
{
    /**
     * @param  list<string>  $usedSourceRowIds  the sub_variable_scores rows averaged into the score
     */
    public function __construct(
        public int $variableId,
        public VariableStatus $status,
        public ?float $score,
        public float $coverage,
        public ?float $confidence,
        public ?GateState $gateState,
        public ?string $gateFailedSubId,
        /** "X of Y" (ADR-010 DEC-11): X = measured contributors, Y = weighted contributors. */
        public int $measuredCount,
        public int $totalCount,
        public array $usedSourceRowIds,
    ) {}
}
