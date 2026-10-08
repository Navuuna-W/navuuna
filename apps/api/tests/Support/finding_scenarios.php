<?php

// Ready-made entities for the findings engine's tests: the scores, record and document a finding
// is raised from. Loaded once from tests/Pest.php.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * A water point both gates passed, whose register promises 500 m³/day and reports 200 m³/day:
 * a 60 % magnitude gap. Returns the entity id.
 *
 * @param  array<string, mixed>  $recordOverrides
 */
function waterPointWithMagnitudeGap(array $recordOverrides = []): string
{
    $entityId = insertCoreEntity(['module' => 'water', 'name' => 'Kibera Water Scheme']);
    $documentId = insertRawDocument();
    insertMatchedWaterRecord($entityId, $documentId, $recordOverrides);

    addSubVariableScore($entityId, '1.1', 100.0);
    addSubVariableScore($entityId, '2.1', 0.0);
    addSubVariableScore($entityId, '2.5', 50.0);
    $magnitudeScoreId = addSubVariableScore($entityId, '2.2', 60.0, 0.9, sourceIds: [$documentId]);
    // The adapter's value is the fraction behind the score (water.md §2.2).
    DB::table('scores.sub_variable_scores')->where('id', $magnitudeScoreId)->update(['value' => '0.6']);

    return $entityId;
}
