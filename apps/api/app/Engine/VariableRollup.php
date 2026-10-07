<?php

// The rollup formula: turns one entity's newest sub-variable scores into one variable score. Pure:
// no database, no Redis. RollupEntity (K-10b) loads the inputs and saves what this returns.

declare(strict_types=1);

namespace App\Engine;

/**
 * Implements Bible §6.2 (weighted rollup over available sub-variables) and §6.3 (three-state gates).
 */
final class VariableRollup
{
    /** An unmeasured gate halves the variable's confidence (Bible §6.3). */
    public const PROVISIONAL_CONFIDENCE_FACTOR = 0.5;

    /**
     * Roll up one variable.
     *
     * @param  array<string, float>  $weights  this variable's sub_id → weight (from Weights)
     * @param  array<string, SubVariableInput>  $inputsBySubId  newest score per sub_id; a sub_id
     *                                                          with no row at all is simply absent
     */
    public function rollUp(int $variableId, array $weights, array $inputsBySubId): VariableResult
    {
        $gateSubId = Gates::gateSubIdFor($variableId);
        $gateState = $gateSubId === null ? null : Gates::stateOf($inputsBySubId[$gateSubId] ?? null);

        $measured = $this->findMeasuredContributors($weights, $inputsBySubId);
        $coverage = $this->calculateCoverage($weights, $measured);

        // Gates veto; contributors average (Bible §6.3). A failed gate is final.
        if ($gateState === GateState::Failed) {
            return $this->cannotAssess($variableId, $weights, $measured, $coverage, $gateState, $gateSubId);
        }

        // Nothing to average: no score rather than a made-up one (CLAUDE.md §4).
        if ($measured === []) {
            return $this->cannotAssess($variableId, $weights, $measured, $coverage, $gateState, null);
        }

        return $this->score($variableId, $weights, $measured, $coverage, $gateState);
    }

    /**
     * The weighted contributors that have a measured score. A missing one is left out, never
     * zeroed (Bible §6.2).
     *
     * @param  array<string, float>  $weights
     * @param  array<string, SubVariableInput>  $inputsBySubId
     * @return array<string, SubVariableInput>
     */
    private function findMeasuredContributors(array $weights, array $inputsBySubId): array
    {
        $measured = [];
        foreach (array_keys($weights) as $subId) {
            $input = $inputsBySubId[$subId] ?? null;
            if ($input !== null && $input->isMeasured()) {
                $measured[$subId] = $input;
            }
        }

        return $measured;
    }

    /**
     * Coverage = Σ(weight of measured) / Σ(weight of all). Bible §6.2.
     *
     * @param  array<string, float>  $weights
     * @param  array<string, SubVariableInput>  $measured
     */
    private function calculateCoverage(array $weights, array $measured): float
    {
        return array_sum(array_intersect_key($weights, $measured)) / array_sum($weights);
    }

    /**
     * Score = Σ(score × weight × confidence) / Σ(weight × confidence) and
     * confidence = Σ(weight × confidence) / Σ(weight), both over measured contributors. Bible §6.2.
     *
     * @param  array<string, float>  $weights
     * @param  array<string, SubVariableInput>  $measured  never empty
     */
    private function score(
        int $variableId,
        array $weights,
        array $measured,
        float $coverage,
        ?GateState $gateState,
    ): VariableResult {
        $weightedScoreSum = 0.0;
        $weightTimesConfidenceSum = 0.0;
        $weightSum = 0.0;
        foreach ($measured as $subId => $input) {
            $weightTimesConfidence = $weights[$subId] * (float) $input->confidence;
            $weightedScoreSum += (float) $input->score * $weightTimesConfidence;
            $weightTimesConfidenceSum += $weightTimesConfidence;
            $weightSum += $weights[$subId];
        }

        $confidence = $weightTimesConfidenceSum / $weightSum;
        $status = VariableStatus::Scored;
        if ($gateState === GateState::Unmeasured) {
            $confidence *= self::PROVISIONAL_CONFIDENCE_FACTOR;
            $status = VariableStatus::Provisional;
        }

        return new VariableResult(
            variableId: $variableId,
            status: $status,
            score: $weightedScoreSum / $weightTimesConfidenceSum,
            coverage: $coverage,
            confidence: $confidence,
            gateState: $gateState,
            gateFailedSubId: null,
            measuredCount: count($measured),
            totalCount: count($weights),
            usedSourceRowIds: $this->sourceRowIds($measured),
        );
    }

    /**
     * No score. Coverage and the "X of Y" counts are still kept: "0 of 4 measured" is worth showing.
     *
     * @param  array<string, float>  $weights
     * @param  array<string, SubVariableInput>  $measured
     */
    private function cannotAssess(
        int $variableId,
        array $weights,
        array $measured,
        float $coverage,
        ?GateState $gateState,
        ?string $gateFailedSubId,
    ): VariableResult {
        return new VariableResult(
            variableId: $variableId,
            status: VariableStatus::CannotAssess,
            score: null,
            coverage: $coverage,
            confidence: null,
            gateState: $gateState,
            gateFailedSubId: $gateFailedSubId,
            measuredCount: count($measured),
            totalCount: count($weights),
            usedSourceRowIds: [],
        );
    }

    /**
     * @param  array<string, SubVariableInput>  $measured
     * @return list<string>
     */
    private function sourceRowIds(array $measured): array
    {
        $rowIds = [];
        foreach ($measured as $input) {
            $rowIds[] = $input->sourceRowId;
        }

        return $rowIds;
    }
}
