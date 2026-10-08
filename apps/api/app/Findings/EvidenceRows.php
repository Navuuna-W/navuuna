<?php

// The rows behind one sub-variable score, sorted by kind. LoadEvidenceRows builds it from the
// score's source_ids; the findings engine turns it into the evidence pack's id lists and fills
// the narrative's placeholders from its rows.

declare(strict_types=1);

namespace App\Findings;

/**
 * The evidence for a finding: every record, observation and satellite reading the score used,
 * and the documents they came from. Each row is the table row as an array, plus `source_name`
 * (the core.sources name), and `record_table` on records. Newest first.
 */
final readonly class EvidenceRows
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<array<string, mixed>>  $observations
     * @param  list<array<string, mixed>>  $eoStats
     * @param  list<string>  $documentIds
     */
    public function __construct(
        public array $records,
        public array $observations,
        public array $eoStats,
        public array $documentIds,
    ) {}

    /**
     * @return list<string>
     */
    public function recordIds(): array
    {
        return self::idsOf($this->records);
    }

    /**
     * @return list<string>
     */
    public function observationIds(): array
    {
        return self::idsOf($this->observations);
    }

    /**
     * @return list<string>
     */
    public function eoStatIds(): array
    {
        return self::idsOf($this->eoStats);
    }

    /**
     * True when at least one row points at real evidence. A finding is a claim about a named
     * party, so the engine never raises one without it (evidence_packs_has_evidence_check).
     */
    public function hasEvidence(): bool
    {
        return $this->records !== [] || $this->observations !== []
            || $this->eoStats !== [] || $this->documentIds !== [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private static function idsOf(array $rows): array
    {
        return array_map(fn (array $row) => (string) $row['id'], $rows);
    }
}
