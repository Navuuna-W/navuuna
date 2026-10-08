<?php

// The safety net behind the stream consumer: every 10 minutes the scheduler runs `engine:sweep`,
// which rolls up any entity whose sub-variable scores are newer than its variable scores. A lost
// signals.batch_written message can therefore leave scores stale for minutes, never for good.

declare(strict_types=1);

namespace App\Engine;

use Illuminate\Database\ConnectionInterface;

/**
 * Implements ADR-004a §4 — reconciliation sweep.
 */
final class ReconciliationSweep
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly RollupEntity $rollupEntity,
    ) {}

    /**
     * Roll up every stale entity. Uses the same idempotent rollup as the stream consumer.
     *
     * @return int how many entities were rolled up
     */
    public function run(): int
    {
        $staleEntityIds = $this->findStaleEntityIds();
        foreach ($staleEntityIds as $entityId) {
            $this->rollupEntity->rollUp($entityId);
        }

        return count($staleEntityIds);
    }

    /**
     * Entities that are not retired and whose newest sub-variable score is newer than their
     * newest variable score, or that have sub-variable scores and no variable score at all.
     *
     * @return list<string>
     */
    public function findStaleEntityIds(): array
    {
        // A missing variable score counts as "older than anything" ('-infinity').
        $rows = $this->database->select(
            "SELECT entities.id
             FROM core.entities AS entities
             JOIN (
                 SELECT entity_id, max(computed_at) AS newest_at
                 FROM scores.sub_variable_scores
                 GROUP BY entity_id
             ) AS sub_variable ON sub_variable.entity_id = entities.id
             LEFT JOIN (
                 SELECT entity_id, max(computed_at) AS newest_at
                 FROM scores.variable_scores
                 GROUP BY entity_id
             ) AS variable ON variable.entity_id = entities.id
             WHERE entities.retired_at IS NULL
               AND sub_variable.newest_at > coalesce(variable.newest_at, '-infinity')
             ORDER BY entities.id"
        );

        $entityIds = [];
        foreach ($rows as $row) {
            $entityIds[] = $row->id;
        }

        return $entityIds;
    }
}
