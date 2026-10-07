<?php

// Decides whether a variable's gate passed, failed or was not measured. Only V1 and V2 have a gate.
// The rule reads the gate's score, never its value label, so the engine knows nothing about water
// or roads (CLAUDE.md §4). Every module scores its gates on the same 0 / 100 scale.

declare(strict_types=1);

namespace App\Engine;

/**
 * Implements Bible §6.3 — three-state gates.
 */
final class Gates
{
    /** 1.1 Presence gates V1. Score 100 = the entity is there, 0 = it is not. */
    public const PRESENCE_GATE = '1.1';

    /** 2.1 Existence gap gates V2. Score 100 = the record says it exists and it does not, 0 = no gap. */
    public const EXISTENCE_GAP_GATE = '2.1';

    /** Gate scores are 0 or 100 (docs/modules/water.md); the midpoint splits them. */
    public const GATE_SCORE_THRESHOLD = 50.0;

    /**
     * The gate sub-variable of a variable, or null for V3–V5, which have none.
     */
    public static function gateSubIdFor(int $variableId): ?string
    {
        return match ($variableId) {
            1 => self::PRESENCE_GATE,
            2 => self::EXISTENCE_GAP_GATE,
            default => null,
        };
    }

    /**
     * The state of a gate, given its newest score (null when no adapter has written one).
     */
    public static function stateOf(?SubVariableInput $gateInput): GateState
    {
        if ($gateInput === null || ! $gateInput->isMeasured()) {
            return GateState::Unmeasured;
        }

        $isScoreHigh = $gateInput->score >= self::GATE_SCORE_THRESHOLD;

        // A high Presence score means "it is there" (pass); a high Existence-gap score means
        // "it is missing" (fail).
        $isPassed = $gateInput->subId === self::PRESENCE_GATE ? $isScoreHigh : ! $isScoreHigh;

        return $isPassed ? GateState::Passed : GateState::Failed;
    }
}
