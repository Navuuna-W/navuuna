<?php

// The only way a finding changes state. The review endpoint (people), the findings engine
// (K-13) and E8 auto-resolve (the system) all call this; nothing else updates flags.flags.state.

declare(strict_types=1);

namespace App\Findings;

use App\Models\AuditEvent;
use App\Models\Flag;
use App\Models\FlagTransition;
use App\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Checks a move is legal, then saves the new state, the history row and the audit row in one
 * transaction. Implements Bible §6.5, ADR-010 (DEC-10, E8), ADR-013 and work pack K-14.
 */
class FlagWorkflow
{
    /** Moves an analyst or admin may make (Bible §6.5, ADR-013). Note: no detected → published. */
    private const MOVES_BY_PERSON = [
        'held' => ['explanation_checked'],
        'explanation_checked' => ['published', 'dismissed'],
        'published' => ['contested'],
        'contested' => ['resolved', 'published'],
    ];

    /** Moves only the system makes: K-13 holds a new finding; E8 closes one whose gap closed. */
    private const MOVES_BY_SYSTEM = [
        'detected' => ['held'],
        'held' => ['dismissed'],
        'explanation_checked' => ['dismissed'],
        'published' => ['resolved'],
    ];

    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly Dispatcher $events,
    ) {}

    /**
     * An analyst or admin moves a finding. Moving to explanation_checked needs the explanation
     * they found (ADR-010 DEC-10).
     *
     * @throws IllegalFlagTransition when the user, move, note or explanation is not allowed.
     */
    public function moveByPerson(Flag $flag, FlagState $toState, User $user, string $note, ?string $explanation = null): void
    {
        if (! $user->role->canSeeUnpublishedFindings()) {
            throw IllegalFlagTransition::notAReviewer();
        }

        $isExplanationMissing = $explanation === null || trim($explanation) === '';
        if ($toState === FlagState::ExplanationChecked && $isExplanationMissing) {
            throw IllegalFlagTransition::explanationRequired();
        }

        $this->move($flag, $toState, $user, $note, $explanation);
    }

    /**
     * The system moves a finding (K-13 holds it; E8 dismisses or resolves it). No user.
     *
     * @throws IllegalFlagTransition when the move or note is not allowed.
     */
    public function moveBySystem(Flag $flag, FlagState $toState, string $note): void
    {
        $this->move($flag, $toState, null, $note, null);
    }

    /**
     * Lock the finding, check the move against its current state, and write all three rows.
     * A null $user means the system.
     */
    private function move(Flag $flag, FlagState $toState, ?User $user, string $note, ?string $explanation): void
    {
        if (trim($note) === '') {
            throw IllegalFlagTransition::noteRequired();
        }

        $this->database->transaction(function () use ($flag, $toState, $user, $note, $explanation) {
            // Read the state under a row lock, so two reviewers can't both move the same finding.
            $lockedFlag = Flag::whereKey($flag->id)->lockForUpdate()->firstOrFail();
            $fromState = $lockedFlag->state;
            $this->assertMoveIsAllowed($fromState, $toState, $user);

            $this->saveState($lockedFlag, $toState);
            $this->recordTransition($lockedFlag, $fromState, $toState, $user, $note);
            $this->recordAuditEvent($lockedFlag, $fromState, $toState, $user, $note, $explanation);

            $this->events->dispatch(new FlagStateChanged(
                $lockedFlag->id, $lockedFlag->entity_id, $fromState, $toState, $user?->id
            ));
        });

        $flag->refresh();
    }

    /**
     * @throws IllegalFlagTransition when $fromState → $toState is not in the actor's table.
     */
    private function assertMoveIsAllowed(FlagState $fromState, FlagState $toState, ?User $user): void
    {
        $allowedMoves = $user === null ? self::MOVES_BY_SYSTEM : self::MOVES_BY_PERSON;
        $allowedTargets = $allowedMoves[$fromState->value] ?? [];

        if (! in_array($toState->value, $allowedTargets, true)) {
            throw IllegalFlagTransition::notAllowed($fromState, $toState, $user === null ? 'system' : 'person');
        }
    }

    /**
     * Update flags.flags. now() is the transaction's start time in PostgreSQL, so every row
     * written by one request carries the same time (ADR-010 DEC-10: same note, user and time).
     * A plain query, because the model's datetime cast can't hold the SQL now().
     */
    private function saveState(Flag $lockedFlag, FlagState $toState): void
    {
        $this->database->table('flags.flags')
            ->where('id', $lockedFlag->id)
            ->update(['state' => $toState->value, 'state_changed_at' => $this->database->raw('now()')]);
    }

    /**
     * Append the history row (flags.transitions) — who, when, why (Bible §6.5).
     */
    private function recordTransition(Flag $lockedFlag, FlagState $fromState, FlagState $toState, ?User $user, string $note): void
    {
        $transition = new FlagTransition;
        $transition->flag_id = $lockedFlag->id;
        $transition->from_state = $fromState->value;
        $transition->to_state = $toState->value;
        $transition->user_id = $user?->id;
        $transition->note = $note;
        $transition->save();
    }

    /**
     * Append the audit row (audit.events, FR-20). The explanation lives in `after` (K-14).
     */
    private function recordAuditEvent(Flag $lockedFlag, FlagState $fromState, FlagState $toState, ?User $user, string $note, ?string $explanation): void
    {
        $auditEvent = new AuditEvent;
        $auditEvent->user_id = $user?->id;
        $auditEvent->action = 'flag_transition';
        $auditEvent->target_table = 'flags.flags';
        $auditEvent->target_id = $lockedFlag->id;
        $auditEvent->before = ['state' => $fromState->value];
        $auditEvent->after = ['state' => $toState->value, 'note' => $note, 'explanation' => $explanation];
        $auditEvent->save();
    }
}
