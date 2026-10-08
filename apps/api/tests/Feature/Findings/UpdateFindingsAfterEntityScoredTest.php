<?php

// Checks that a rollup is followed by the findings engine with no command typed: EntityScored
// raises and holds the finding, the re-roll that holding triggers does not raise it twice, and
// a finding whose gap has closed is resolved (E8).

declare(strict_types=1);

use App\Engine\RollupEntity;
use App\Findings\FlagState;
use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['engine.modules_path' => base_path('tests/fixtures/modules')]);
});

test('rolling an entity up raises and holds its finding', function () {
    $entityId = waterPointWithMagnitudeGap();

    app(RollupEntity::class)->rollUp($entityId);

    expect(Flag::sole()->state)->toBe(FlagState::Held)
        ->and(Flag::sole()->entity_id)->toBe($entityId);
});

test('the re-roll after holding a finding does not raise it again', function () {
    $entityId = waterPointWithMagnitudeGap();

    app(RollupEntity::class)->rollUp($entityId);
    app(RollupEntity::class)->rollUp($entityId);

    expect(Flag::count())->toBe(1);
});

test('rolling an entity up resolves a published finding whose gap stayed closed for a week', function () {
    $entityId = insertCoreEntity(['module' => 'water']);
    addSubVariableScore($entityId, '2.2', 6.0, computedAt: '2026-10-01 10:00:00+00');
    addSubVariableScore($entityId, '2.2', 4.0, computedAt: '2026-10-08 10:00:00+00');
    $findingId = insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'severity' => 'medium', 'state' => 'published']);

    app(RollupEntity::class)->rollUp($entityId);

    expect(Flag::findOrFail($findingId)->state)->toBe(FlagState::Resolved);
});
