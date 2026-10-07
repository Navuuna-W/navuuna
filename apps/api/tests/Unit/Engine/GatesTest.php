<?php

// Checks the three gate states of Bible §6.3 for both gates: 1.1 Presence (high score passes)
// and 2.1 Existence gap (high score fails). Pure unit tests: no database, no Laravel app.

declare(strict_types=1);

use App\Engine\Gates;
use App\Engine\GateState;

test('only V1 and V2 have a gate', function () {
    expect(Gates::gateSubIdFor(1))->toBe('1.1')
        ->and(Gates::gateSubIdFor(2))->toBe('2.1')
        ->and(Gates::gateSubIdFor(3))->toBeNull()
        ->and(Gates::gateSubIdFor(4))->toBeNull()
        ->and(Gates::gateSubIdFor(5))->toBeNull();
});

test('a present entity passes the presence gate', function () {
    $gateInput = measured('1.1', 100, 0.7);

    $state = Gates::stateOf($gateInput);

    expect($state)->toBe(GateState::Passed);
});

test('an absent entity fails the presence gate', function () {
    $gateInput = measured('1.1', 0, 0.7);

    $state = Gates::stateOf($gateInput);

    expect($state)->toBe(GateState::Failed);
});

test('an existence gap fails the existence-gap gate', function () {
    $gateInput = measured('2.1', 100, 0.9);

    $state = Gates::stateOf($gateInput);

    expect($state)->toBe(GateState::Failed);
});

test('no existence gap passes the existence-gap gate', function () {
    $gateInput = measured('2.1', 0, 0.9);

    $state = Gates::stateOf($gateInput);

    expect($state)->toBe(GateState::Passed);
});

test('a score exactly on the threshold counts as high', function () {
    $presence = measured('1.1', Gates::GATE_SCORE_THRESHOLD, 0.7);
    $existenceGap = measured('2.1', Gates::GATE_SCORE_THRESHOLD, 0.7);

    $presenceState = Gates::stateOf($presence);
    $existenceGapState = Gates::stateOf($existenceGap);

    expect($presenceState)->toBe(GateState::Passed)
        ->and($existenceGapState)->toBe(GateState::Failed);
});

test('a gate the adapter could not measure is unmeasured', function () {
    $gateInput = notMeasured('1.1');

    $state = Gates::stateOf($gateInput);

    expect($state)->toBe(GateState::Unmeasured);
});

test('a gate with no row at all is unmeasured', function () {
    $state = Gates::stateOf(null);

    expect($state)->toBe(GateState::Unmeasured);
});

test('a gate measured with zero confidence is unmeasured', function () {
    $gateInput = measured('1.1', 100, 0.0);

    $state = Gates::stateOf($gateInput);

    expect($state)->toBe(GateState::Unmeasured);
});
