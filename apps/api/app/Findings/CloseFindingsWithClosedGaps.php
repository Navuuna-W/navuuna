<?php

// E8 auto-resolve for one entity. Runs with the findings engine after every rollup: for each of
// the entity's open findings whose gap has stayed closed (GapClosure), the system closes it — a
// published finding is resolved, a held one is dismissed and never published. Evidence is kept.

declare(strict_types=1);

namespace App\Findings;

use App\Models\Flag;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Implements ADR-010 E8 and work pack K-13 ("a published finding whose gap closes → resolved,
 * system actor, evidence kept").
 */
final class CloseFindingsWithClosedGaps
{
    /** Where each closable state goes when its gap closes. Contested waits for a person (ADR-013). */
    private const CLOSED_STATE_FOR = [
        'held' => FlagState::Dismissed,
        'explanation_checked' => FlagState::Dismissed,
        'published' => FlagState::Resolved,
    ];

    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly FlagWorkflow $flagWorkflow,
    ) {}

    /**
     * Close every open finding of the entity whose gap has closed.
     *
     * @return int how many findings were closed
     */
    public function close(string $entityId): int
    {
        $openFindings = Flag::where('entity_id', $entityId)
            ->whereIn('state', array_keys(self::CLOSED_STATE_FOR))
            ->get();

        $closedCount = 0;
        foreach ($openFindings as $finding) {
            if ($this->closeIfGapClosed($finding)) {
                $closedCount++;
            }
        }

        return $closedCount;
    }

    private function closeIfGapClosed(Flag $finding): bool
    {
        $closedBelowScore = FindingThresholds::autoResolveBelowScore($finding->sub_id);
        if ($closedBelowScore === null) {
            return false;
        }

        $closingScores = GapClosure::closingScores($this->loadScoresNewestFirst($finding), $closedBelowScore);
        if ($closingScores === null) {
            return false;
        }

        [$earlier, $newest] = $closingScores;
        $note = "Gap closed (ADR-010 E8): {$finding->sub_id} scored {$earlier->score} on "
            ."{$earlier->computedAt->toDateString()} and {$newest->score} on {$newest->computedAt->toDateString()}, "
            ."both below {$closedBelowScore}.";
        $this->flagWorkflow->moveBySystem($finding, self::CLOSED_STATE_FOR[$finding->state->value], $note);

        return true;
    }

    /**
     * The finding's sub-variable scores for its entity, newest first.
     *
     * @return list<ScoreAtTime>
     */
    private function loadScoresNewestFirst(Flag $finding): array
    {
        $rows = $this->database->table('scores.sub_variable_scores')
            ->where('entity_id', $finding->entity_id)
            ->where('sub_id', $finding->sub_id)
            ->orderByDesc('computed_at')
            ->orderByDesc('id')
            ->get(['score', 'computed_at']);

        return $rows->map(fn (object $row) => new ScoreAtTime(
            $row->score === null ? null : (float) $row->score,
            CarbonImmutable::parse((string) $row->computed_at),
        ))->values()->all();
    }
}
