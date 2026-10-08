<?php

// Checks that a rollup is followed by the findings engine with no command typed: EntityScored
// raises and holds the finding, and the re-roll that holding triggers does not raise it twice.

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
