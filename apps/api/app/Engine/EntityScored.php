<?php

// Announced by RollupEntity after an entity's five variable scores are committed. Listeners: the
// lens re-apply and the score broadcast (Austine's lane, Bible §6.6 and §11.1).

declare(strict_types=1);

namespace App\Engine;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An entity has new variable scores. Implements K-10 (emits EntityScored) and ADR-010 DEC-08
 * (the lens is re-applied after a V2 re-roll). ShouldDispatchAfterCommit: listeners only hear
 * about scores that were really saved.
 */
class EntityScored implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $entityId,
    ) {}
}
