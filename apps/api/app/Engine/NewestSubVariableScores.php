<?php

// Loads what the rollup averages: the newest scores.sub_variable_scores row per sub-variable for
// one entity. The table is append-only (ADR-004a), so older rows are history, never inputs.

declare(strict_types=1);

namespace App\Engine;

use Illuminate\Database\ConnectionInterface;

/**
 * Reads the rollup's inputs for one entity. Implements Bible §6.2 ("newest sub scores", K-10).
 */
final class NewestSubVariableScores
{
    public function __construct(private readonly ConnectionInterface $database) {}

    /**
     * The newest row for every sub-variable this entity has. A sub-variable with no row at all is
     * absent from the result; VariableRollup treats that the same as not measured.
     *
     * @return array<string, SubVariableInput> sub_id → newest score
     */
    public function forEntity(string $entityId): array
    {
        // DISTINCT ON keeps the first row per sub_id in ORDER BY order: newest computed_at, and
        // the newest id (UUIDv7) when two rows share a timestamp. Uses the
        // sub_variable_scores_entity_sub_computed_index.
        $rows = $this->database->select(
            'SELECT DISTINCT ON (sub_id) id, sub_id, score, confidence
             FROM scores.sub_variable_scores
             WHERE entity_id = ?
             ORDER BY sub_id, computed_at DESC, id DESC',
            [$entityId],
        );

        $inputsBySubId = [];
        foreach ($rows as $row) {
            $inputsBySubId[$row->sub_id] = new SubVariableInput(
                subId: $row->sub_id,
                score: $row->score === null ? null : (float) $row->score,
                confidence: $row->confidence === null ? null : (float) $row->confidence,
                sourceRowId: $row->id,
            );
        }

        return $inputsBySubId;
    }
}
