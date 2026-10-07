<?php

// Thrown by FlagWorkflow when a move is not allowed: not in its table, a blank note, a missing
// explanation, or a user who is not an analyst or admin. The review endpoint turns it into a 422.

declare(strict_types=1);

namespace App\Findings;

use RuntimeException;

/**
 * A finding move that FlagWorkflow refused. Nothing was written. Implements K-14.
 */
class IllegalFlagTransition extends RuntimeException
{
    /**
     * The move is not in FlagWorkflow's table for this kind of actor.
     */
    public static function notAllowed(FlagState $from, FlagState $to, string $actor): self
    {
        return new self("A {$actor} cannot move a finding from {$from->value} to {$to->value}.");
    }

    /**
     * Every move needs a note saying why (Bible §6.5, ADR-010 DEC-10).
     */
    public static function noteRequired(): self
    {
        return new self('A note is required to move a finding.');
    }

    /**
     * Checking for an explanation means recording what was found (ADR-010 DEC-10).
     */
    public static function explanationRequired(): self
    {
        return new self('An explanation is required to mark a finding explanation_checked.');
    }

    /**
     * "Needs more evidence" keeps a finding held, so it only applies to a held one (DEC-10).
     */
    public static function notHeld(FlagState $state): self
    {
        return new self("Only a held finding can get a note without moving; this one is {$state->value}.");
    }

    /**
     * Only analysts and admins review findings (CLAUDE.md §4, FR-11).
     */
    public static function notAReviewer(): self
    {
        return new self('Only analysts and admins can move a finding.');
    }
}
