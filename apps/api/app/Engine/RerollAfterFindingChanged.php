<?php

// Listens for FlagStateChanged (K-14) and rolls the finding's entity up again. That is how a
// sub-variable held back from V2 while its finding was under review comes back once the finding
// is published or dismissed, and how a newly held one drops out straight away.

declare(strict_types=1);

namespace App\Engine;

use App\Findings\FlagStateChanged;

/**
 * Implements ADR-010 DEC-08: "When the finding is published or dismissed, the entity's V2 is
 * re-rolled and the lens re-applied". RollupEntity announces EntityScored, which re-applies the
 * lens. Every move re-rolls, not only publish and dismiss: rolling up is idempotent and cheap.
 */
final class RerollAfterFindingChanged
{
    public function __construct(private readonly RollupEntity $rollupEntity) {}

    public function handle(FlagStateChanged $event): void
    {
        $this->rollupEntity->rollUp($event->entityId);
    }
}
