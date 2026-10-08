<?php

// Checks E8 auto-resolve against the database: a published finding whose gap closed is resolved,
// a held one is dismissed, both by the system with the two scores in the note — and a contested
// or still-open gap is left alone.

declare(strict_types=1);

use App\Findings\CloseFindingsWithClosedGaps;
use App\Findings\FlagState;
use App\Models\Flag;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A 2.2 finding in the given state, raised at 45, whose newest runs scored $newerScores (oldest
 * first, one per week from 1 Oct). Returns the finding's id.
 *
 * @param  list<float>  $newerScores
 */
function magnitudeFindingWithLaterScores(string $state, array $newerScores): string
{
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '2.2', 45.0, computedAt: '2026-09-20 10:00:00+00');
    foreach ($newerScores as $week => $score) {
        $computedAt = CarbonImmutable::parse('2026-10-01 10:00:00+00')->addWeeks($week)->toIso8601String();
        addSubVariableScore($entityId, '2.2', $score, computedAt: $computedAt);
    }

    return insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'severity' => 'medium', 'state' => $state]);
}

function closeFindingsOf(string $findingId): int
{
    $entityId = (string) Flag::findOrFail($findingId)->entity_id;

    return app(CloseFindingsWithClosedGaps::class)->close($entityId);
}

test('a published finding whose gap stayed closed for a week is resolved', function () {
    $findingId = magnitudeFindingWithLaterScores('published', [6.0, 4.0]);

    $closedCount = closeFindingsOf($findingId);

    expect($closedCount)->toBe(1)
        ->and(Flag::findOrFail($findingId)->state)->toBe(FlagState::Resolved);
});

test('a held finding whose gap closed is dismissed, never published', function (string $state) {
    $findingId = magnitudeFindingWithLaterScores($state, [6.0, 4.0]);

    closeFindingsOf($findingId);

    expect(Flag::findOrFail($findingId)->state)->toBe(FlagState::Dismissed);
})->with(['held', 'explanation_checked']);

test('the system closes it with both scores in the note', function () {
    $findingId = magnitudeFindingWithLaterScores('published', [6.0, 4.0]);

    closeFindingsOf($findingId);

    $transition = DB::table('flags.transitions')->where('flag_id', $findingId)->sole();
    expect($transition->user_id)->toBeNull()
        ->and($transition->note)->toBe('Gap closed (ADR-010 E8): 2.2 scored 6 on 2026-10-01 and 4 on 2026-10-08, both below 10.');
});

test('a finding whose gap is still open is left alone', function () {
    $findingId = magnitudeFindingWithLaterScores('published', [6.0, 25.0]);

    $closedCount = closeFindingsOf($findingId);

    expect($closedCount)->toBe(0)
        ->and(Flag::findOrFail($findingId)->state)->toBe(FlagState::Published);
});

test('a contested finding waits for a person even when its gap closed', function () {
    $findingId = magnitudeFindingWithLaterScores('contested', [6.0, 4.0]);

    $closedCount = closeFindingsOf($findingId);

    expect($closedCount)->toBe(0)
        ->and(Flag::findOrFail($findingId)->state)->toBe(FlagState::Contested);
});

test('a finding on a sub-variable with no raise threshold is left alone', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '2.5', 0.0, computedAt: '2026-10-01 10:00:00+00');
    addSubVariableScore($entityId, '2.5', 0.0, computedAt: '2026-10-08 10:00:00+00');
    $findingId = insertFinding(['entity_id' => $entityId, 'sub_id' => '2.5', 'severity' => 'low', 'state' => 'held']);

    $closedCount = closeFindingsOf($findingId);

    expect($closedCount)->toBe(0)
        ->and(Flag::findOrFail($findingId)->state)->toBe(FlagState::Held);
});
