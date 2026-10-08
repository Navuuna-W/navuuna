<?php

// Listens for EntityScored (K-10) and runs the findings engine for that entity: first closes
// findings whose gap has closed (E8), then raises new ones. This is how findings move without
// anyone typing a command: signal run → rollup → EntityScored → here.
// Holding a finding re-rolls the entity (RerollAfterFindingChanged), which fires EntityScored
// again; that second pass finds the finding already open and raises nothing, so the chain ends.

declare(strict_types=1);

namespace App\Findings;

use App\Engine\EntityScored;

/**
 * Implements work pack K-13 (findings raised and auto-resolved after every rollup) and FR-10.
 */
final class UpdateFindingsAfterEntityScored
{
    public function __construct(
        private readonly CloseFindingsWithClosedGaps $closeFindingsWithClosedGaps,
        private readonly RaiseFindingsForEntity $raiseFindingsForEntity,
    ) {}

    public function handle(EntityScored $event): void
    {
        $this->closeFindingsWithClosedGaps->close($event->entityId);
        $this->raiseFindingsForEntity->raise($event->entityId);
    }
}
