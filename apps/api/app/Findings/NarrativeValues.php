<?php

// Everything a narrative's value paths can read for one finding, as plain rows. The findings
// engine builds it from the entity, the evidence rows and the entity's newest scores; the
// narrative filler reads it. Each row is a table row as an array (jsonb columns decoded).

declare(strict_types=1);

namespace App\Findings;

/**
 * The rows behind entity.*, record.*, observation.*, eo_stat.*, score.* and score[sub_id].*
 * value paths (see the header of a findings.yml). A row is null when the finding has none.
 */
final readonly class NarrativeValues
{
    /**
     * @param  array<string, mixed>  $entity  the core.entities row
     * @param  array<string, mixed>|null  $record  the newest record behind the score
     * @param  array<string, mixed>|null  $observation  the newest observation behind the score
     * @param  array<string, mixed>|null  $eoStat  the newest satellite reading behind the score
     * @param  array<string, mixed>  $score  the scores.sub_variable_scores row that raised the finding
     * @param  array<string, array<string, mixed>>  $newestScoresBySubId  sub_id → newest score row of the entity
     */
    public function __construct(
        public array $entity,
        public ?array $record,
        public ?array $observation,
        public ?array $eoStat,
        public array $score,
        public array $newestScoresBySubId,
    ) {}

    /**
     * The row a value path starts from: "record" → the record row, "score[1.2]" → the newest
     * 1.2 score. Null when there is no such row.
     *
     * @return array<string, mixed>|null
     */
    public function rowFor(string $subject): ?array
    {
        if (preg_match('/^score\[(.+)\]$/', $subject, $match) === 1) {
            return $this->newestScoresBySubId[$match[1]] ?? null;
        }

        return match ($subject) {
            'entity' => $this->entity,
            'record' => $this->record,
            'observation' => $this->observation,
            'eo_stat' => $this->eoStat,
            'score' => $this->score,
            default => null,
        };
    }
}
