<?php

// Checks which findings RaiseRules raises for one entity: the five rules of ADR-010 Decision 1
// (DEC-09) and the Bible §6.3 rule that a provisional entity never gets one. Pure unit tests.

declare(strict_types=1);

use App\Engine\SubVariableInput;
use App\Findings\FindingToRaise;
use App\Findings\RaiseRules;
use App\Findings\Severity;

/**
 * Both gates measured and passed: the entity is there and the record matches it.
 *
 * @return list<SubVariableInput>
 */
function passedGates(): array
{
    return [measured('1.1', 100, 0.8), measured('2.1', 0, 0.8)];
}

/**
 * The sub_ids of the findings RaiseRules returned, in order.
 *
 * @param  list<FindingToRaise>  $findingsToRaise
 * @return list<string>
 */
function raisedSubIds(array $findingsToRaise): array
{
    return array_map(fn (FindingToRaise $finding) => $finding->subId, $findingsToRaise);
}

test('a magnitude gap over the threshold raises a finding with its severity', function () {
    $scores = bySubId([...passedGates(), measured('2.2', 60, 0.7)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toHaveCount(1)
        ->and($findingsToRaise[0]->subId)->toBe('2.2')
        ->and($findingsToRaise[0]->severity)->toBe(Severity::Medium)
        ->and($findingsToRaise[0]->score->sourceRowId)->toBe('row-2.2');
});

test('a gap exactly at the threshold raises a finding', function () {
    $scores = bySubId([...passedGates(), measured('2.2', 30, 0.5), measured('2.4', 50, 0.5)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect(raisedSubIds($findingsToRaise))->toBe(['2.2', '2.4']);
});

test('a gap under the threshold raises nothing', function () {
    $scores = bySubId([...passedGates(), measured('2.2', 29.9, 0.9), measured('2.4', 0, 0.9)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toBe([]);
});

test('a score under the minimum confidence raises nothing', function () {
    $scores = bySubId([...passedGates(), measured('2.2', 90, 0.49)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toBe([]);
});

test('an unmeasured sub-variable raises nothing', function () {
    $scores = bySubId([...passedGates(), notMeasured('2.2'), notMeasured('2.4')]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toBe([]);
});

test('a sub-variable with an open finding does not raise a second one', function () {
    $scores = bySubId([...passedGates(), measured('2.2', 80, 0.9), measured('2.4', 100, 0.9)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, ['2.2']);

    expect(raisedSubIds($findingsToRaise))->toBe(['2.4']);
});

test('record staleness never raises a finding alone', function () {
    $scores = bySubId([...passedGates(), measured('2.5', 100, 0.9)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toBe([]);
});

test('an unmeasured gate makes the entity provisional, so nothing is raised', function (array $gateScores) {
    $scores = bySubId([...$gateScores, measured('2.2', 90, 0.9)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toBe([]);
})->with([
    'presence not measured' => [[notMeasured('1.1'), measured('2.1', 0, 0.8)]],
    'presence never scored' => [[measured('2.1', 0, 0.8)]],
    'existence gap not measured' => [[measured('1.1', 100, 0.8), notMeasured('2.1')]],
]);

test('an existence gap raises only the existence-gap finding, and it is high', function () {
    $scores = bySubId([
        measured('1.1', 0, 0.8),
        measured('2.1', 100, 0.8),
        measured('2.2', 90, 0.9),
        measured('2.4', 100, 0.9),
    ]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect(raisedSubIds($findingsToRaise))->toBe(['2.1'])
        ->and($findingsToRaise[0]->severity)->toBe(Severity::High);
});

test('an existence gap that already has an open finding raises nothing else', function () {
    $scores = bySubId([measured('1.1', 0, 0.8), measured('2.1', 100, 0.8), measured('2.2', 90, 0.9)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, ['2.1']);

    expect($findingsToRaise)->toBe([]);
});

test('an existence gap under the minimum confidence raises nothing', function () {
    $scores = bySubId([measured('1.1', 0, 0.8), measured('2.1', 100, 0.4)]);

    $findingsToRaise = RaiseRules::findingsToRaise($scores, []);

    expect($findingsToRaise)->toBe([]);
});
