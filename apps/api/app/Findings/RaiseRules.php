<?php

// Decides which findings to raise for one entity, from its newest sub-variable scores. Pure: no
// database. The findings engine loads the scores and the open findings, asks this class, then
// saves what it returns. It reads sub-variable IDs and scores only, never module data.

declare(strict_types=1);

namespace App\Findings;

use App\Engine\Gates;
use App\Engine\GateState;
use App\Engine\SubVariableInput;

/**
 * Implements ADR-010 Decision 1 (DEC-09) and Bible §6.3.
 */
final class RaiseRules
{
    /**
     * The findings to raise for one entity.
     *
     * Inputs: the entity's newest score per sub-variable, keyed by sub_id, and the sub_ids
     * that already have an open finding. Output: one FindingToRaise per gap that meets every
     * DEC-09 rule; an empty list when the entity's gates were not measured.
     *
     * @param  array<string, SubVariableInput>  $newestScoresBySubId
     * @param  list<string>  $subIdsWithOpenFinding
     * @return list<FindingToRaise>
     */
    public static function findingsToRaise(array $newestScoresBySubId, array $subIdsWithOpenFinding): array
    {
        if (! self::areBothGatesMeasured($newestScoresBySubId)) {
            return [];
        }

        $findingsToRaise = [];
        foreach (self::subIdsToCheck($newestScoresBySubId) as $subId) {
            $score = $newestScoresBySubId[$subId] ?? null;
            $hasOpenFinding = in_array($subId, $subIdsWithOpenFinding, true);
            if ($score === null || $hasOpenFinding || ! self::isGapBigEnough($score)) {
                continue;
            }

            $severity = FindingThresholds::severityFor($subId, (float) $score->score);
            $findingsToRaise[] = new FindingToRaise($subId, $severity, $score);
        }

        return $findingsToRaise;
    }

    /**
     * DEC-09 rule 1 and Bible §6.3: never a finding for a provisional entity. An unmeasured
     * gate means "we couldn't look", and a finding would accuse someone of a gap we never saw.
     * A failed gate is fine: an absent entity with a record is exactly the 2.1 finding.
     *
     * @param  array<string, SubVariableInput>  $newestScoresBySubId
     */
    private static function areBothGatesMeasured(array $newestScoresBySubId): bool
    {
        foreach ([Gates::PRESENCE_GATE, Gates::EXISTENCE_GAP_GATE] as $gateSubId) {
            $gateState = Gates::stateOf($newestScoresBySubId[$gateSubId] ?? null);
            if ($gateState === GateState::Unmeasured) {
                return false;
            }
        }

        return true;
    }

    /**
     * An existence gap is the strongest finding: when 2.1 shows a gap, every other 2.x check
     * is skipped (Bible §6.9, docs/modules/water.md §2.1).
     *
     * @param  array<string, SubVariableInput>  $newestScoresBySubId
     * @return list<string>
     */
    private static function subIdsToCheck(array $newestScoresBySubId): array
    {
        $existenceGapState = Gates::stateOf($newestScoresBySubId[Gates::EXISTENCE_GAP_GATE] ?? null);
        if ($existenceGapState === GateState::Failed) {
            return [Gates::EXISTENCE_GAP_GATE];
        }

        return array_keys(FindingThresholds::RAISE_AT_SCORE);
    }

    /**
     * DEC-09 rules 2–4: measured, confident enough, and at or above the raise threshold.
     */
    private static function isGapBigEnough(SubVariableInput $score): bool
    {
        $raiseAtScore = FindingThresholds::raiseAtScore($score->subId);
        if ($raiseAtScore === null || ! $score->isMeasured()) {
            return false;
        }

        $isConfidentEnough = $score->confidence >= FindingThresholds::MIN_FINDING_CONFIDENCE;

        return $isConfidentEnough && $score->score >= $raiseAtScore;
    }
}
