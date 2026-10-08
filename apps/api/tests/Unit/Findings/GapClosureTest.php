<?php

// Checks the E8 rule (ADR-010): a gap is closed only when runs at least seven days apart — and
// every run between them — scored below the auto-resolve score. Pure unit tests.

declare(strict_types=1);

use App\Findings\GapClosure;
use App\Findings\ScoreAtTime;
use Carbon\CarbonImmutable;

/** 2.2 Magnitude gap: raised at 30, closed below 10. */
const MAGNITUDE_GAP_CLOSED_BELOW = 10.0;

/**
 * Scores newest first, from [score, date] pairs.
 *
 * @param  list<array{0: float|null, 1: string}>  $scoresAndDates
 * @return list<ScoreAtTime>
 */
function scoreHistory(array $scoresAndDates): array
{
    return array_map(
        fn (array $scoreAndDate) => new ScoreAtTime($scoreAndDate[0], CarbonImmutable::parse($scoreAndDate[1])),
        $scoresAndDates,
    );
}

test('two runs seven days apart below the closed score close the gap', function () {
    $history = scoreHistory([[4.0, '2026-10-08'], [6.0, '2026-10-01'], [45.0, '2026-09-20']]);

    $closingScores = GapClosure::closingScores($history, MAGNITUDE_GAP_CLOSED_BELOW);

    expect($closingScores)->not->toBeNull()
        ->and($closingScores[0]->score)->toBe(6.0)
        ->and($closingScores[1]->score)->toBe(4.0);
});

test('runs in between must all be below too', function () {
    $history = scoreHistory([[4.0, '2026-10-08'], [5.0, '2026-10-05'], [6.0, '2026-10-01']]);

    $closingScores = GapClosure::closingScores($history, MAGNITUDE_GAP_CLOSED_BELOW);

    expect($closingScores[0]->score ?? null)->toBe(6.0);
});

test('the gap is still open when', function (array $scoresAndDates) {
    $history = scoreHistory($scoresAndDates);

    $closingScores = GapClosure::closingScores($history, MAGNITUDE_GAP_CLOSED_BELOW);

    expect($closingScores)->toBeNull();
})->with([
    'there are no scores' => [[]],
    'only one run is below' => [[[4.0, '2026-10-08']]],
    'the runs below are only six days apart' => [[[4.0, '2026-10-08'], [6.0, '2026-10-02']]],
    'the newest run is not below' => [[[12.0, '2026-10-08'], [4.0, '2026-10-01']]],
    'a run in between hovers back above (hysteresis)' => [[[4.0, '2026-10-08'], [15.0, '2026-10-04'], [6.0, '2026-10-01']]],
    'a run in between was not measured' => [[[4.0, '2026-10-08'], [null, '2026-10-04'], [6.0, '2026-10-01']]],
    'a run sits exactly at the closed score' => [[[4.0, '2026-10-08'], [10.0, '2026-10-01']]],
]);
