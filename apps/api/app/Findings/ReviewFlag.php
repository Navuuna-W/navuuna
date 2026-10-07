<?php

// Turns one review-screen button into FlagWorkflow calls. The review endpoint calls this once per
// request; Publish and Dismiss record two moves, so they share one transaction.

declare(strict_types=1);

namespace App\Findings;

use App\Models\Flag;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Applies a ReviewAction to one finding. Implements ADR-010 DEC-10 (Publish / Dismiss / Needs
 * more evidence) and ADR-013 (Contest / Resolve / Reject contest).
 */
class ReviewFlag
{
    /** The explanation recorded when an analyst publishes: they looked and found none (DEC-10). */
    public const NO_EXPLANATION_FOUND = 'none found';

    public function __construct(
        private readonly FlagWorkflow $workflow,
        private readonly ConnectionInterface $database,
    ) {}

    /**
     * Apply $action. $explanation is required for Dismiss and ignored otherwise.
     *
     * @throws IllegalFlagTransition when the action doesn't fit the finding's state or the input.
     */
    public function apply(Flag $flag, User $user, ReviewAction $action, string $note, ?string $explanation): void
    {
        match ($action) {
            ReviewAction::Publish => $this->checkThenClose($flag, $user, $note, self::NO_EXPLANATION_FOUND, FlagState::Published),
            ReviewAction::Dismiss => $this->checkThenClose($flag, $user, $note, $explanation, FlagState::Dismissed),
            ReviewAction::NeedsMoreEvidence => $this->workflow->addNoteToHeldFinding($flag, $user, $note),
            ReviewAction::Contest => $this->workflow->moveByPerson($flag, FlagState::Contested, $user, $note),
            ReviewAction::Resolve => $this->workflow->moveByPerson($flag, FlagState::Resolved, $user, $note),
            ReviewAction::RejectContest => $this->workflow->moveByPerson($flag, FlagState::Published, $user, $note),
        };
    }

    /**
     * held → explanation_checked → $finalState, both with the same note, user and time. One
     * transaction, so a failure in the second move undoes the first (DEC-10: one request).
     */
    private function checkThenClose(Flag $flag, User $user, string $note, ?string $explanation, FlagState $finalState): void
    {
        $this->database->transaction(function () use ($flag, $user, $note, $explanation, $finalState) {
            $this->workflow->moveByPerson($flag, FlagState::ExplanationChecked, $user, $note, $explanation);
            $this->workflow->moveByPerson($flag, $finalState, $user, $note);
        });
    }
}
