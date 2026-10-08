<?php

// Checks the raise thresholds and severities of ADR-010 Decision 1 (DEC-09).
// Pure unit tests: no database, no Laravel app.

declare(strict_types=1);

use App\Findings\FindingThresholds;
use App\Findings\Severity;

test('only 2.1, 2.2 and 2.4 raise findings', function () {
    expect(FindingThresholds::raiseAtScore('2.1'))->toBe(100.0)
        ->and(FindingThresholds::raiseAtScore('2.2'))->toBe(30.0)
        ->and(FindingThresholds::raiseAtScore('2.4'))->toBe(50.0)
        ->and(FindingThresholds::raiseAtScore('2.3'))->toBeNull()
        ->and(FindingThresholds::raiseAtScore('2.5'))->toBeNull()
        ->and(FindingThresholds::raiseAtScore('1.2'))->toBeNull();
});

test('an existence gap is always high', function () {
    $severity = FindingThresholds::severityFor('2.1', 100.0);

    expect($severity)->toBe(Severity::High);
});

test('a magnitude gap is graded at 50 and 75', function (float $score, Severity $expectedSeverity) {
    $severity = FindingThresholds::severityFor('2.2', $score);

    expect($severity)->toBe($expectedSeverity);
})->with([
    'lowest raised score' => [30.0, Severity::Low],
    'just under medium' => [49.9, Severity::Low],
    'medium from 50' => [50.0, Severity::Medium],
    'just under high' => [74.9, Severity::Medium],
    'high from 75' => [75.0, Severity::High],
    'delivers nothing' => [100.0, Severity::High],
]);

test('a status gap is low when intermittent and high when not working', function () {
    $intermittentSeverity = FindingThresholds::severityFor('2.4', 50.0);
    $notWorkingSeverity = FindingThresholds::severityFor('2.4', 100.0);

    expect($intermittentSeverity)->toBe(Severity::Low)
        ->and($notWorkingSeverity)->toBe(Severity::High);
});
