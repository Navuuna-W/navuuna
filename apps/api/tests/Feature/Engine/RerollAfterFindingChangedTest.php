<?php

// Checks ADR-010 DEC-08 end to end: when an analyst publishes a held finding through FlagWorkflow,
// the entity's V2 is rolled up again and the sub-variable that was held back comes back.

declare(strict_types=1);

use App\Auth\Role;
use App\Engine\RollupEntity;
use App\Findings\FlagState;
use App\Findings\FlagWorkflow;
use App\Models\Flag;
use App\Models\User;
use App\Models\VariableScore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The newest stored V2 score of an entity.
 */
function newestDiscrepancyScore(string $entityId): VariableScore
{
    return VariableScore::query()
        ->where('entity_id', $entityId)
        ->where('variable_id', 2)
        ->orderByDesc('computed_at')
        ->orderByDesc('id')
        ->firstOrFail();
}

test('publishing a held finding re-rolls V2 with its sub-variable back in', function () {
    $entityId = insertCoreEntity();
    addSubVariableScore($entityId, '2.1', 0.0, 0.9);
    addSubVariableScore($entityId, '2.2', 90.0, 1.0);
    $findingId = insertFinding(['entity_id' => $entityId, 'sub_id' => '2.2', 'state' => 'explanation_checked']);
    app(RollupEntity::class)->rollUp($entityId);
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    app(FlagWorkflow::class)->moveByPerson(Flag::findOrFail($findingId), FlagState::Published, $analyst, 'Checked: no explanation.');

    $discrepancy = newestDiscrepancyScore($entityId);
    expect($discrepancy->score)->toBe(90.0)
        ->and($discrepancy->inputs)->toMatchArray(['held_back_sub_ids' => []]);
});
