<?php

// Finds the 2.x sub-variables of an entity that have a finding no viewer may see yet. The rollup
// leaves them out of V2, so a gap under review never leaks through a score, colour or count.

declare(strict_types=1);

namespace App\Engine;

use App\Findings\FlagState;
use Illuminate\Database\ConnectionInterface;

/**
 * Implements ADR-010 DEC-08: V2 while a finding is held.
 */
final class SubVariablesUnderReview
{
    /**
     * Open findings that only analysts may see. DEC-08 names held and explanation_checked;
     * detected (on its way to held) and contested (hidden while looked at, ADR-013) are hidden
     * from viewers too, so they are left out for the same reason.
     */
    public const HIDDEN_OPEN_STATES = [
        FlagState::Detected,
        FlagState::Held,
        FlagState::ExplanationChecked,
        FlagState::Contested,
    ];

    public function __construct(private readonly ConnectionInterface $database) {}

    /**
     * The sub_ids (2.1–2.5) with a hidden open finding for this entity.
     *
     * @return list<string>
     */
    public function forEntity(string $entityId): array
    {
        $states = array_map(fn (FlagState $state): string => $state->value, self::HIDDEN_OPEN_STATES);
        $placeholders = implode(', ', array_fill(0, count($states), '?'));

        $rows = $this->database->select(
            "SELECT DISTINCT sub_id FROM flags.flags
             WHERE entity_id = ? AND state IN ({$placeholders})
             ORDER BY sub_id",
            [$entityId, ...$states],
        );

        $subIds = [];
        foreach ($rows as $row) {
            $subIds[] = $row->sub_id;
        }

        return $subIds;
    }
}
