<?php

// Checks the rollup formula (Bible §6.2) and the three gate states (Bible §6.3) on hand-worked
// numbers. Pure unit tests: no database, no Laravel app.

declare(strict_types=1);

use App\Engine\GateState;
use App\Engine\VariableRollup;
use App\Engine\VariableStatus;

/** V1's contributors as in weights.yml v1; 1.1 Presence is the gate and has no weight. */
const V1_WEIGHTS = ['1.2' => 0.40, '1.3' => 0.25, '1.4' => 0.25, '1.5' => 0.10];

/** V3 has no gate. */
const V3_WEIGHTS = ['3.1' => 0.50, '3.2' => 0.50];

test('a passed gate with every contributor measured gives a scored variable', function () {
    $inputs = bySubId([
        measured('1.1', 100, 0.9),
        measured('1.2', 80, 1.0),
        measured('1.3', 60, 1.0),
        measured('1.4', 40, 1.0),
        measured('1.5', 20, 1.0),
    ]);

    $result = (new VariableRollup)->rollUp(1, V1_WEIGHTS, $inputs);

    // (80×0.40 + 60×0.25 + 40×0.25 + 20×0.10) / 1.00 = 59
    expect($result->status)->toBe(VariableStatus::Scored)
        ->and($result->score)->toEqualWithDelta(59.0, 0.0001)
        ->and($result->coverage)->toEqualWithDelta(1.0, 0.0001)
        ->and($result->confidence)->toEqualWithDelta(1.0, 0.0001)
        ->and($result->gateState)->toBe(GateState::Passed)
        ->and($result->gateFailedSubId)->toBeNull()
        ->and($result->measuredCount)->toBe(4)
        ->and($result->totalCount)->toBe(4)
        ->and($result->usedSourceRowIds)->toBe(['row-1.2', 'row-1.3', 'row-1.4', 'row-1.5']);
});

test('a missing contributor is left out of the score, never counted as zero', function () {
    $inputs = bySubId([measured('3.1', 80, 1.0), notMeasured('3.2')]);

    $result = (new VariableRollup)->rollUp(3, V3_WEIGHTS, $inputs);

    expect($result->status)->toBe(VariableStatus::Scored)
        ->and($result->score)->toEqualWithDelta(80.0, 0.0001)
        ->and($result->coverage)->toEqualWithDelta(0.5, 0.0001)
        ->and($result->measuredCount)->toBe(1)
        ->and($result->totalCount)->toBe(2)
        ->and($result->gateState)->toBeNull();
});

test('a contributor with no row at all is treated the same as one not measured', function () {
    $inputs = bySubId([measured('3.1', 80, 1.0)]);

    $result = (new VariableRollup)->rollUp(3, V3_WEIGHTS, $inputs);

    expect($result->score)->toEqualWithDelta(80.0, 0.0001)
        ->and($result->coverage)->toEqualWithDelta(0.5, 0.0001);
});

test('score is weighted by confidence and confidence is weighted by weight', function () {
    $inputs = bySubId([measured('3.1', 100, 0.8), measured('3.2', 0, 0.2)]);

    $result = (new VariableRollup)->rollUp(3, V3_WEIGHTS, $inputs);

    // score = (100×0.5×0.8 + 0×0.5×0.2) / (0.5×0.8 + 0.5×0.2) = 40 / 0.5 = 80
    // confidence = (0.5×0.8 + 0.5×0.2) / (0.5 + 0.5) = 0.5
    expect($result->score)->toEqualWithDelta(80.0, 0.0001)
        ->and($result->confidence)->toEqualWithDelta(0.5, 0.0001);
});

test('confidence is averaged over measured contributors only', function () {
    $inputs = bySubId([measured('1.1', 100, 1.0), measured('1.2', 50, 0.6)]);

    $result = (new VariableRollup)->rollUp(1, V1_WEIGHTS, $inputs);

    expect($result->confidence)->toEqualWithDelta(0.6, 0.0001)
        ->and($result->coverage)->toEqualWithDelta(0.40, 0.0001);
});

test('a contributor with zero confidence counts as not measured', function () {
    $inputs = bySubId([measured('3.1', 80, 1.0), measured('3.2', 10, 0.0)]);

    $result = (new VariableRollup)->rollUp(3, V3_WEIGHTS, $inputs);

    expect($result->score)->toEqualWithDelta(80.0, 0.0001)
        ->and($result->measuredCount)->toBe(1);
});

test('a sub-variable that is not in the weights is ignored', function () {
    $inputs = bySubId([measured('3.1', 80, 1.0), measured('9.9', 0, 1.0)]);

    $result = (new VariableRollup)->rollUp(3, V3_WEIGHTS, $inputs);

    expect($result->score)->toEqualWithDelta(80.0, 0.0001)
        ->and($result->totalCount)->toBe(2);
});

test('a failed presence gate makes the variable cannot_assess with no score', function () {
    $inputs = bySubId([measured('1.1', 0, 0.7), measured('1.2', 80, 1.0)]);

    $result = (new VariableRollup)->rollUp(1, V1_WEIGHTS, $inputs);

    expect($result->status)->toBe(VariableStatus::CannotAssess)
        ->and($result->score)->toBeNull()
        ->and($result->confidence)->toBeNull()
        ->and($result->gateState)->toBe(GateState::Failed)
        ->and($result->gateFailedSubId)->toBe('1.1')
        ->and($result->coverage)->toEqualWithDelta(0.40, 0.0001)
        ->and($result->measuredCount)->toBe(1)
        ->and($result->usedSourceRowIds)->toBe([]);
});

test('an unmeasured gate makes the variable provisional with half confidence', function () {
    $inputs = bySubId([notMeasured('1.1'), measured('1.2', 80, 0.8)]);

    $result = (new VariableRollup)->rollUp(1, V1_WEIGHTS, $inputs);

    expect($result->status)->toBe(VariableStatus::Provisional)
        ->and($result->score)->toEqualWithDelta(80.0, 0.0001)
        ->and($result->confidence)->toEqualWithDelta(0.4, 0.0001)
        ->and($result->gateState)->toBe(GateState::Unmeasured)
        ->and($result->gateFailedSubId)->toBeNull();
});

test('an unmeasured gate with nothing measured is cannot_assess and keeps the unmeasured gate', function () {
    $inputs = bySubId([notMeasured('1.1'), notMeasured('1.2')]);

    $result = (new VariableRollup)->rollUp(1, V1_WEIGHTS, $inputs);

    expect($result->status)->toBe(VariableStatus::CannotAssess)
        ->and($result->score)->toBeNull()
        ->and($result->gateState)->toBe(GateState::Unmeasured)
        ->and($result->gateFailedSubId)->toBeNull();
});

test('a passed gate with nothing measured is cannot_assess and still reports 0 of 4', function () {
    $inputs = bySubId([measured('1.1', 100, 0.9)]);

    $result = (new VariableRollup)->rollUp(1, V1_WEIGHTS, $inputs);

    expect($result->status)->toBe(VariableStatus::CannotAssess)
        ->and($result->gateState)->toBe(GateState::Passed)
        ->and($result->coverage)->toEqualWithDelta(0.0, 0.0001)
        ->and($result->measuredCount)->toBe(0)
        ->and($result->totalCount)->toBe(4);
});
