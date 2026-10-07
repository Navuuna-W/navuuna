<?php

// The three states of a gate sub-variable (1.1 Presence, 2.1 Existence gap). Stored in
// scores.variable_scores.gate_status for V1 and V2; null for V3–V5, which have no gate.

declare(strict_types=1);

namespace App\Engine;

/**
 * Implements Bible §6.3 — "we looked and it isn't there" and "we couldn't look" are different facts.
 */
enum GateState: string
{
    /** Measured, and the entity passed: roll up normally. */
    case Passed = 'passed';

    /** Measured, and the entity failed: cannot_assess, nothing averaged. */
    case Failed = 'failed';

    /** Not measured: roll up what is there, provisional, confidence × 0.5. */
    case Unmeasured = 'unmeasured';
}
