<?php

// Writes one new finding: the flags.flags row, its evidence pack, and the automatic move from
// detected to held, all in one transaction. Called by RaiseFindingsForEntity once the rules,
// the evidence and the narrative have all said yes.

declare(strict_types=1);

namespace App\Findings;

use App\Models\Flag;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Implements Bible §6.5 (detected → held automatically) and work pack K-13 (evidence pack:
 * record_ids, observation_ids, document_ids, eo_stat_ids, narrative, adapter_version).
 */
final class SaveHeldFinding
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly FlagWorkflow $flagWorkflow,
    ) {}

    /**
     * Save the finding and hold it.
     *
     * Inputs: the entity, the finding RaiseRules chose, its evidence rows, its filled narrative,
     * and the score row that raised it. Output: false when another run raised the same finding
     * first (the one-open-finding-per-entity-×-sub index refused it), true otherwise.
     *
     * @param  array<string, mixed>  $scoreRow  the scores.sub_variable_scores row
     */
    public function save(string $entityId, FindingToRaise $finding, EvidenceRows $evidence, string $narrative, FilledNarrative $filledNarrative, array $scoreRow): bool
    {
        try {
            $this->database->transaction(function () use ($entityId, $finding, $evidence, $narrative, $filledNarrative, $scoreRow): void {
                $flagId = $this->insertFlag($entityId, $finding, $filledNarrative, $scoreRow);
                $this->insertEvidencePack($flagId, $evidence, $narrative, (string) $scoreRow['adapter_version']);

                $note = "Raised by the findings engine: {$finding->subId} scored {$finding->score->score} "
                    ."with confidence {$finding->score->confidence} (ADR-010 DEC-09).";
                $this->flagWorkflow->moveBySystem(Flag::findOrFail($flagId), FlagState::Held, $note);
            });
        } catch (UniqueConstraintViolationException) {
            // DEC-09 rule 5: someone raised it between our read and our write. Not an error.
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $scoreRow
     */
    private function insertFlag(string $entityId, FindingToRaise $finding, FilledNarrative $filledNarrative, array $scoreRow): string
    {
        // gap is "the size of the gap in the sub-variable's own unit; null when it is a yes/no".
        $gap = is_int($scoreRow['value']) || is_float($scoreRow['value']) ? $scoreRow['value'] : null;

        return (string) $this->database->table('flags.flags')->insertGetId([
            'entity_id' => $entityId,
            'sub_id' => $finding->subId,
            'severity' => $finding->severity->value,
            'state' => FlagState::Detected->value,
            'declared' => json_encode($filledNarrative->declared, JSON_THROW_ON_ERROR),
            'observed' => json_encode($filledNarrative->observed, JSON_THROW_ON_ERROR),
            'gap' => $gap,
        ]);
    }

    private function insertEvidencePack(string $flagId, EvidenceRows $evidence, string $narrative, string $adapterVersion): void
    {
        $this->database->table('flags.evidence_packs')->insert([
            'flag_id' => $flagId,
            'narrative' => $narrative,
            'adapter_version' => $adapterVersion,
            'record_ids' => self::toUuidArray($evidence->recordIds()),
            'observation_ids' => self::toUuidArray($evidence->observationIds()),
            'document_ids' => self::toUuidArray($evidence->documentIds),
            'eo_stat_ids' => self::toUuidArray($evidence->eoStatIds()),
        ]);
    }

    /**
     * A PostgreSQL uuid[] literal, e.g. {0190…,0190…}. UUIDs hold no commas or quotes.
     *
     * @param  list<string>  $ids
     */
    private static function toUuidArray(array $ids): string
    {
        return '{'.implode(',', $ids).'}';
    }
}
