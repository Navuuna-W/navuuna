<?php

// Decides whether a finding's gap has closed for good (E8), from the sub-variable's score history.
// Pure: CloseFindingsWithClosedGaps loads the history and acts on the answer.

declare(strict_types=1);

namespace App\Findings;

/**
 * Implements ADR-010 E8: closed when two runs at least MIN_DAYS_BETWEEN_RESOLVING_RUNS apart —
 * and every run between them — scored below the auto-resolve score (raise threshold − 20).
 * The margin and the wait stop a value hovering at the threshold from flipping the finding.
 */
final class GapClosure
{
    /**
     * The two scores that show the gap closed — [oldest, newest] — or null when it has not.
     *
     * Inputs: the sub-variable's scores, newest first, and the score to stay below.
     * A run that was not measured breaks the chain: "we couldn't look" is not "it closed".
     *
     * @param  list<ScoreAtTime>  $scoresNewestFirst
     * @return array{0: ScoreAtTime, 1: ScoreAtTime}|null
     */
    public static function closingScores(array $scoresNewestFirst, float $closedBelowScore): ?array
    {
        $newest = $scoresNewestFirst[0] ?? null;
        if ($newest === null) {
            return null;
        }

        foreach ($scoresNewestFirst as $earlier) {
            if ($earlier->score === null || $earlier->score >= $closedBelowScore) {
                return null;
            }

            $daysBetween = $earlier->computedAt->diffInDays($newest->computedAt);
            if ($daysBetween >= FindingThresholds::MIN_DAYS_BETWEEN_RESOLVING_RUNS) {
                return [$earlier, $newest];
            }
        }

        return null;
    }
}
