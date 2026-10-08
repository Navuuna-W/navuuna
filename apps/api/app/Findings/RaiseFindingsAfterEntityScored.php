<?php

// Listens for EntityScored (K-10) and runs the findings engine for that entity. This is how
// findings get raised without anyone typing a command: signal run → rollup → EntityScored → here.
// Holding a finding re-rolls the entity (RerollAfterFindingChanged), which fires EntityScored
// again; that second pass finds the finding already open and raises nothing, so the chain ends.

declare(strict_types=1);

namespace App\Findings;

use App\Engine\EntityScored;

/**
 * Implements work pack K-13 (findings raised after every rollup) and FR-10.
 */
final class RaiseFindingsAfterEntityScored
{
    public function __construct(private readonly RaiseFindingsForEntity $raiseFindingsForEntity) {}

    public function handle(EntityScored $event): void
    {
        $this->raiseFindingsForEntity->raise($event->entityId);
    }
}
