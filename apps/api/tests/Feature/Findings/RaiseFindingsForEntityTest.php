<?php

// Checks the findings engine end to end for one entity, against the water findings.yml stub:
// a gap becomes a held finding with its evidence pack and narrative — and nothing is raised for
// a provisional entity, a missing value, a score without evidence, or an entity with no module
// file. Saving itself (transaction, race) is checked in SaveHeldFindingTest.

declare(strict_types=1);

use App\Findings\FlagState;
use App\Findings\RaiseFindingsForEntity;
use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['engine.modules_path' => base_path('tests/fixtures/modules')]);
});

function raiseFindingsFor(string $entityId): int
{
    return app(RaiseFindingsForEntity::class)->raise($entityId);
}

test('a magnitude gap becomes a held finding with its severity and declared and observed values', function () {
    $entityId = waterPointWithMagnitudeGap();

    $raisedCount = raiseFindingsFor($entityId);

    $finding = Flag::sole();
    expect($raisedCount)->toBe(1)
        ->and($finding->entity_id)->toBe($entityId)
        ->and($finding->sub_id)->toBe('2.2')
        ->and($finding->severity)->toBe('medium')
        ->and($finding->state)->toBe(FlagState::Held)
        ->and($finding->declared)->toMatchArray(['value' => '500', 'date' => '1 Mar 2024'])
        ->and($finding->observed)->toMatchArray(['value' => '200'])
        ->and($finding->gap)->toEqual(0.6);
});

test('the evidence pack has the filled narrative, its closing line and the record behind the score', function () {
    $entityId = waterPointWithMagnitudeGap();

    raiseFindingsFor($entityId);

    $pack = DB::table('flags.evidence_packs')->sole();
    $recordId = DB::table('records.water_schemes')->where('entity_id', $entityId)->value('id');
    expect($pack->narrative)->toContain('lists a rated yield of 500 m³/day for Kibera Water Scheme (record dated 1 Mar 2024)')
        ->and($pack->narrative)->toContain('— 60% below the rated figure.')
        ->and($pack->narrative)->toEndWith('The official record is 50 days older than our latest observation.')
        ->and($pack->record_ids)->toBe("{{$recordId}}");
});

test('running again raises nothing while the finding is open', function () {
    $entityId = waterPointWithMagnitudeGap();
    raiseFindingsFor($entityId);

    $raisedCount = raiseFindingsFor($entityId);

    expect($raisedCount)->toBe(0)
        ->and(Flag::count())->toBe(1);
});

test('the closing line is left off when record staleness was not measured', function () {
    $entityId = waterPointWithMagnitudeGap();
    addSubVariableScore($entityId, '2.5', null, computedAt: '2026-10-07 11:00:00+00');

    raiseFindingsFor($entityId);

    expect(DB::table('flags.evidence_packs')->value('narrative'))->toEndWith('below the rated figure.');
});

test('nothing is raised for an entity whose presence gate was not measured', function () {
    $entityId = waterPointWithMagnitudeGap();
    addSubVariableScore($entityId, '1.1', null, computedAt: '2026-10-07 11:00:00+00');

    $raisedCount = raiseFindingsFor($entityId);

    expect($raisedCount)->toBe(0)
        ->and(Flag::count())->toBe(0);
});

test('nothing is raised when a narrative value is missing', function () {
    $entityId = waterPointWithMagnitudeGap(['record_date' => null]);

    $raisedCount = raiseFindingsFor($entityId);

    expect($raisedCount)->toBe(0)
        ->and(Flag::count())->toBe(0);
});

test('nothing is raised when the score cites no evidence row', function () {
    $entityId = waterPointWithMagnitudeGap();
    addSubVariableScore($entityId, '2.2', 60.0, 0.9, computedAt: '2026-10-07 11:00:00+00', sourceIds: [insertCoreSource()]);

    $raisedCount = raiseFindingsFor($entityId);

    expect($raisedCount)->toBe(0);
});

test('nothing is raised for a module without a findings file, a shared area or a retired entity', function (array $entityChange) {
    $entityId = waterPointWithMagnitudeGap();
    DB::table('core.entities')->where('id', $entityId)->update($entityChange);

    $raisedCount = raiseFindingsFor($entityId);

    expect($raisedCount)->toBe(0);
})->with([
    'no findings file' => [['module' => 'land']],
    'no module' => [['module' => null]],
    'retired' => [['retired_at' => '2026-10-01 10:00:00+00']],
]);
