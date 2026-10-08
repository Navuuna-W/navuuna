<?php

// Rolls up one entity: loads its newest sub-variable scores, works out the five variable scores
// with VariableRollup and appends them to scores.variable_scores in one transaction. Called by
// `engine:rollup`, the stream consumer (K-10c) and the reconciliation sweep (K-10d).

declare(strict_types=1);

namespace App\Engine;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Implements Bible §6.2–6.3 for one entity, ADR-010 DEC-08 (V2 leaves out sub-variables under
 * review) and work pack K-10 (one transaction per entity, weight_version, engine_version).
 * Idempotent: rolling up twice with the same inputs stores the same scores (ADR-004a §3).
 */
final class RollupEntity
{
    /** Stored on every variable score; bump it when the rollup rules change. */
    public const ENGINE_VERSION = '1.0.0';

    /** V2 Discrepancy is the variable whose sub-variables can be under review (DEC-08). */
    private const DISCREPANCY_VARIABLE_ID = 2;

    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly NewestSubVariableScores $newestSubVariableScores,
        private readonly SubVariablesUnderReview $subVariablesUnderReview,
        private readonly VariableRollup $variableRollup,
        private readonly Weights $weights,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Roll up one entity and announce EntityScored.
     *
     * @return bool false when the entity does not exist or is retired (nothing written)
     */
    public function rollUp(string $entityId): bool
    {
        if (! $this->isActiveEntity($entityId)) {
            return false;
        }

        $this->database->transaction(function () use ($entityId): void {
            $inputsBySubId = $this->newestSubVariableScores->forEntity($entityId);
            $heldBackSubIds = $this->subVariablesUnderReview->forEntity($entityId);

            foreach (Weights::VARIABLE_IDS as $variableId) {
                $this->rollUpVariable($entityId, $variableId, $inputsBySubId, $heldBackSubIds);
            }

            $this->events->dispatch(new EntityScored($entityId));
        });

        return true;
    }

    /**
     * Retired entities are never scored (work pack K-09: the runner skips them too).
     */
    private function isActiveEntity(string $entityId): bool
    {
        return $this->database->table('core.entities')
            ->where('id', $entityId)
            ->whereNull('retired_at')
            ->exists();
    }

    /**
     * @param  array<string, SubVariableInput>  $inputsBySubId
     * @param  list<string>  $heldBackSubIds
     */
    private function rollUpVariable(string $entityId, int $variableId, array $inputsBySubId, array $heldBackSubIds): void
    {
        // A finding under review must not reach anyone through V2 (DEC-08): its sub-variable is
        // treated as not available, so coverage drops honestly. Other variables are unaffected.
        if ($variableId !== self::DISCREPANCY_VARIABLE_ID) {
            $heldBackSubIds = [];
        }
        $availableInputs = array_diff_key($inputsBySubId, array_flip($heldBackSubIds));

        $weights = $this->weights->forVariable($variableId);
        $result = $this->variableRollup->rollUp($variableId, $weights, $availableInputs);

        $this->database->table('scores.variable_scores')->insert([
            'entity_id' => $entityId,
            'variable_id' => $variableId,
            'score' => $result->score,
            'coverage' => $result->coverage,
            'confidence' => $result->confidence,
            'status' => $result->status->value,
            'gate_status' => $result->gateState?->value,
            'gate_failed_sub_id' => $result->gateFailedSubId,
            'weight_version' => $this->weights->weightVersion,
            'engine_version' => self::ENGINE_VERSION,
            'inputs' => json_encode($this->describeInputs($result, $weights, $heldBackSubIds), JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * What the score was rolled up from, kept in variable_scores.inputs so anyone can trace it
     * back to the sub-variable rows (Bible §7.3 provenance).
     *
     * @param  array<string, float>  $weights
     * @param  list<string>  $heldBackSubIds
     * @return array<string, mixed>
     */
    private function describeInputs(VariableResult $result, array $weights, array $heldBackSubIds): array
    {
        return [
            // "X of Y" (ADR-010 DEC-11).
            'measured_count' => $result->measuredCount,
            'total_count' => $result->totalCount,
            'weights' => $weights,
            'averaged_sub_variable_score_ids' => $result->usedSourceRowIds,
            'held_back_sub_ids' => $heldBackSubIds,
        ];
    }
}
