<?php

// Follows one sub-variable score's source_ids to the rows they name. An adapter may put a record
// row's id, its document's id, an observation id or a satellite reading id there (water.md rule 2),
// so every kind is looked up. It finds records tables by asking the database, like the signal
// service's input loader, so it knows nothing about water or roads (CLAUDE.md §4).

declare(strict_types=1);

namespace App\Findings;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Loads the evidence behind a score. Implements work pack K-13 ("declared / observed built from
 * the rows named in source_ids") and Bible §7.3 (every claim traces to its source).
 */
final class LoadEvidenceRows
{
    /** Every records table matched to entities has an entity_id column; the review queue has none. */
    private const FIND_RECORDS_TABLES_SQL =
        "SELECT table_name FROM information_schema.columns
         WHERE table_schema = 'records' AND column_name = 'entity_id'
         ORDER BY table_name";

    /** Table names come from the catalog, but they go into SQL text, so check their shape. */
    private const TABLE_NAME_PATTERN = '/^[a-z_][a-z0-9_]*$/';

    public function __construct(private readonly ConnectionInterface $database) {}

    /**
     * The evidence rows of one scores.sub_variable_scores row. Rows of another entity are
     * ignored, and so are observations whose contributor withdrew consent (DEC-14).
     */
    public function forScore(string $scoreRowId): EvidenceRows
    {
        $records = $this->loadRecords($scoreRowId);

        $directDocuments = $this->database->select(
            'SELECT document.id FROM raw.documents AS document
             JOIN scores.sub_variable_scores AS score ON score.id = ?
             WHERE document.id = ANY(score.source_ids)',
            [$scoreRowId],
        );
        $directDocumentIds = array_map(fn (object $document) => (string) $document->id, $directDocuments);
        $recordDocumentIds = array_map(fn (array $record) => (string) $record['document_id'], $records);

        return new EvidenceRows(
            records: $records,
            observations: $this->loadObservations($scoreRowId),
            eoStats: $this->loadEoStats($scoreRowId),
            documentIds: array_values(array_unique([...$directDocumentIds, ...$recordDocumentIds])),
        );
    }

    /**
     * Records named directly, or through their document, newest first. Each row carries its
     * table name and the name of the source its document came from.
     *
     * @return list<array<string, mixed>>
     */
    private function loadRecords(string $scoreRowId): array
    {
        $records = [];
        foreach ($this->database->select(self::FIND_RECORDS_TABLES_SQL) as $table) {
            $tableName = (string) $table->table_name;
            if (preg_match(self::TABLE_NAME_PATTERN, $tableName) !== 1) {
                throw new RuntimeException("Unexpected records table name: {$tableName}");
            }

            $records = [...$records, ...$this->loadRows(
                "SELECT to_jsonb(record)
                     || jsonb_build_object('record_table', '{$tableName}', 'source_name', source.name) AS row
                 FROM records.{$tableName} AS record
                 JOIN raw.documents AS document ON document.id = record.document_id
                 JOIN core.sources AS source ON source.id = document.source_id
                 JOIN scores.sub_variable_scores AS score ON score.id = ?
                 WHERE record.entity_id = score.entity_id
                   AND (record.id = ANY(score.source_ids) OR record.document_id = ANY(score.source_ids))
                 ORDER BY record.id DESC",
                $scoreRowId,
            )];
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadObservations(string $scoreRowId): array
    {
        return $this->loadRows(
            "SELECT to_jsonb(observation) || jsonb_build_object('source_name', source.name) AS row
             FROM core.observations AS observation
             JOIN core.sources AS source ON source.id = observation.source_id
             LEFT JOIN core.consents AS consent ON consent.id = observation.consent_id
             JOIN scores.sub_variable_scores AS score ON score.id = ?
             WHERE observation.id = ANY(score.source_ids)
               AND observation.entity_id = score.entity_id
               AND consent.withdrawn_at IS NULL
             ORDER BY observation.observed_at DESC",
            $scoreRowId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadEoStats(string $scoreRowId): array
    {
        return $this->loadRows(
            "SELECT to_jsonb(eo_stat) || jsonb_build_object('source_name', source.name) AS row
             FROM raw.eo_stats AS eo_stat
             JOIN core.sources AS source ON source.id = eo_stat.source_id
             JOIN scores.sub_variable_scores AS score ON score.id = ?
             WHERE eo_stat.id = ANY(score.source_ids) AND eo_stat.entity_id = score.entity_id
             ORDER BY eo_stat.acquired_at DESC",
            $scoreRowId,
        );
    }

    /**
     * Runs a query whose one column `row` is jsonb, and decodes each row to an array.
     *
     * @return list<array<string, mixed>>
     */
    private function loadRows(string $sql, string $scoreRowId): array
    {
        $rows = [];
        foreach ($this->database->select($sql, [$scoreRowId]) as $result) {
            /** @var array<string, mixed> $row */
            $row = json_decode((string) $result->row, associative: true, flags: JSON_THROW_ON_ERROR);
            $rows[] = $row;
        }

        return $rows;
    }
}
