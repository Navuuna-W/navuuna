<?php

// Announced by FlagWorkflow after a finding's move is committed. Listeners: the V2 re-roll and
// lens re-apply (ADR-010 DEC-08) and the `flag.changed` broadcast (Bible §11.1, Austine).

declare(strict_types=1);

namespace App\Findings;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A finding moved from one state to another. Implements K-14 (emits FlagStateChanged).
 * ShouldDispatchAfterCommit: listeners only hear about a move that was really saved.
 */
class FlagStateChanged implements ShouldDispatchAfterCommit
{
    /**
     * @param  string  $flagId  The finding that moved.
     * @param  string  $entityId  The entity it is about, so listeners can re-roll its V2.
     * @param  ?string  $userId  Who moved it; null when the system did (K-13, E8).
     */
    public function __construct(
        public readonly string $flagId,
        public readonly string $entityId,
        public readonly FlagState $fromState,
        public readonly FlagState $toState,
        public readonly ?string $userId,
    ) {}
}
