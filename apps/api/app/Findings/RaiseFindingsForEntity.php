<?php

// The findings engine for one entity (K-13). Runs after the rollup has scored the entity: reads
// its newest sub-variable scores and open findings, asks RaiseRules which gaps to raise, builds
// each one's evidence and narrative, and hands it to SaveHeldFinding. Knows nothing about water
// or roads: the module's findings.yml supplies the words and the fields.

declare(strict_types=1);

namespace App\Findings;

use App\Engine\ModuleFlags;
use App\Engine\SubVariableInput;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Implements FR-10, ADR-010 Decision 1 (DEC-09), Bible §6.3 / §6.5 and work pack K-13.
 */
final class RaiseFindingsForEntity
{
    /** Findings stay open until dismissed or resolved (flags_one_open_per_entity_sub_index). */
    private const CLOSED_STATES = ['dismissed', 'resolved'];

    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly LoadEvidenceRows $loadEvidenceRows,
        private readonly SaveHeldFinding $saveHeldFinding,
        private readonly LoggerInterface $logger,
        private readonly ModuleFlags $moduleFlags,
        private readonly string $modulesPath,
    ) {}

    /**
     * Raise every finding the entity's newest scores call for.
     *
     * @return int how many findings were raised and held
     */
    public function raise(string $entityId): int
    {
        $entity = $this->loadActiveEntity($entityId);
        $findingsFile = $entity === null ? null : $this->loadFindingsFile($entity);
        if ($entity === null || $findingsFile === null) {
            return 0;
        }

        $newestScoreRows = $this->loadNewestScoreRows($entityId);
        $findingsToRaise = RaiseRules::findingsToRaise(self::toInputs($newestScoreRows), $this->loadOpenFindingSubIds($entityId));

        $raisedCount = 0;
        foreach ($findingsToRaise as $finding) {
            if ($this->raiseOne($entity, $findingsFile, $finding, $newestScoreRows)) {
                $raisedCount++;
            }
        }

        return $raisedCount;
    }

    /**
     * Build the evidence and narrative for one finding and save it. Any missing piece skips the
     * finding with a warning: a claim about a named party is never raised half-made.
     *
     * @param  array<string, mixed>  $entity
     * @param  array<string, array<string, mixed>>  $newestScoreRows
     */
    private function raiseOne(array $entity, FindingsFile $findingsFile, FindingToRaise $finding, array $newestScoreRows): bool
    {
        $logContext = ['entity_id' => $entity['id'], 'sub_id' => $finding->subId];
        $template = $findingsFile->templateFor($finding->subId);
        $evidence = $this->loadEvidenceRows->forScore($finding->score->sourceRowId);
        $values = new NarrativeValues(
            entity: $entity,
            record: $evidence->records[0] ?? null,
            observation: $evidence->observations[0] ?? null,
            eoStat: $evidence->eoStats[0] ?? null,
            score: $newestScoreRows[$finding->subId],
            newestScoresBySubId: $newestScoreRows,
        );
        $filledNarrative = $template === null ? null : NarrativeFiller::fill($template, $values);

        if ($filledNarrative === null || ! $evidence->hasEvidence()) {
            $this->logger->warning('Finding not raised: no narrative template, value or evidence.', $logContext);

            return false;
        }

        $narrative = $this->withClosingLine($filledNarrative->text, $findingsFile, $values);

        return $this->saveHeldFinding->save((string) $entity['id'], $finding, $evidence, $narrative, $filledNarrative, $newestScoreRows[$finding->subId]);
    }

    /**
     * Append the module's closing line (record age) when all its values are there; the
     * finding stands without it.
     */
    private function withClosingLine(string $narrative, FindingsFile $findingsFile, NarrativeValues $values): string
    {
        $closingLine = $findingsFile->closingLine === null ? null : NarrativeFiller::fill($findingsFile->closingLine, $values);

        return $closingLine === null ? $narrative : "{$narrative} {$closingLine->text}";
    }

    /**
     * The entity's id, name, type and module, or null when it does not exist or is retired.
     *
     * @return array<string, mixed>|null
     */
    private function loadActiveEntity(string $entityId): ?array
    {
        $entity = $this->database->table('core.entities')
            ->select(['id', 'name', 'entity_type', 'module', 'external_ref'])
            ->where('id', $entityId)
            ->whereNull('retired_at')
            ->first();

        return $entity === null ? null : (array) $entity;
    }

    /**
     * The entity's module's findings.yml, or null when the module has none (e.g. shared areas)
     * or is switched off in MODULES_ENABLED (ADR-012 §3) — then no finding is raised for it.
     *
     * @param  array<string, mixed>  $entity
     */
    private function loadFindingsFile(array $entity): ?FindingsFile
    {
        if ($entity['module'] === null || ! $this->moduleFlags->isModuleEnabled($entity['module'])) {
            return null;
        }

        $path = "{$this->modulesPath}/{$entity['module']}/findings.yml";
        if (! is_file($path)) {
            return null;
        }

        return FindingsFile::fromFile($path);
    }

    /**
     * The newest scores.sub_variable_scores row per sub-variable, as arrays (jsonb decoded).
     * Same ordering as the rollup's NewestSubVariableScores, so both see the same scores.
     *
     * @return array<string, array<string, mixed>> sub_id → row
     */
    private function loadNewestScoreRows(string $entityId): array
    {
        $results = $this->database->select(
            'SELECT DISTINCT ON (sub_id) sub_id, to_jsonb(score_row) AS row
             FROM scores.sub_variable_scores AS score_row
             WHERE entity_id = ?
             ORDER BY sub_id, computed_at DESC, id DESC',
            [$entityId],
        );

        $rowsBySubId = [];
        foreach ($results as $result) {
            /** @var array<string, mixed> $row */
            $row = json_decode((string) $result->row, associative: true, flags: JSON_THROW_ON_ERROR);
            $rowsBySubId[(string) $result->sub_id] = $row;
        }

        return $rowsBySubId;
    }

    /**
     * @param  array<string, array<string, mixed>>  $scoreRows
     * @return array<string, SubVariableInput>
     */
    private static function toInputs(array $scoreRows): array
    {
        $inputs = [];
        foreach ($scoreRows as $subId => $row) {
            $score = is_numeric($row['score']) ? (float) $row['score'] : null;
            $confidence = is_numeric($row['confidence']) ? (float) $row['confidence'] : null;
            $inputs[$subId] = new SubVariableInput($subId, $score, $confidence, (string) $row['id']);
        }

        return $inputs;
    }

    /**
     * @return list<string>
     */
    private function loadOpenFindingSubIds(string $entityId): array
    {
        return $this->database->table('flags.flags')
            ->where('entity_id', $entityId)
            ->whereNotIn('state', self::CLOSED_STATES)
            ->pluck('sub_id')
            ->map(fn (mixed $subId) => (string) $subId)
            ->values()
            ->all();
    }
}
