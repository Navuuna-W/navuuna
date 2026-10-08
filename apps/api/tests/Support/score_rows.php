<?php

// Helpers that insert scores.sub_variable_scores rows the way the Python signal runner writes
// them, for the rollup engine's database tests. Loaded once from tests/Pest.php.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Return the id of a registered adapter for this sub-variable, registering one the first time.
 */
function findOrInsertAdapter(string $subId): string
{
    $adapterId = DB::table('core.adapters')->where('sub_id', $subId)->value('id');
    if ($adapterId !== null) {
        return (string) $adapterId;
    }

    return (string) DB::table('core.adapters')->insertGetId([
        'module' => 'test', 'sub_id' => $subId, 'version' => '1.0.0', 'signal_description' => "Test {$subId}",
        'code_ref' => "modules/test/adapters/{$subId}.py", 'entity_types' => '{point}',
    ]);
}

/**
 * Insert one sub-variable score and return its id. A null score writes a null_not_measured row
 * with a reason, as the runner does — never a 0 (CLAUDE.md §4). A measured score cites a fresh
 * source unless $sourceIds names the rows it was built from.
 *
 * @param  list<string>|null  $sourceIds
 */
function addSubVariableScore(
    string $entityId,
    string $subId,
    ?float $score,
    float $confidence = 0.8,
    string $computedAt = '2026-10-07 10:00:00+00',
    ?array $sourceIds = null,
): string {
    $isMeasured = $score !== null;
    $sourceIds ??= $isMeasured ? [insertCoreSource()] : [];

    return (string) DB::table('scores.sub_variable_scores')->insertGetId([
        'entity_id' => $entityId,
        'sub_id' => $subId,
        'value' => $isMeasured ? (string) $score : null,
        'score' => $score,
        'confidence' => $isMeasured ? $confidence : null,
        'status' => $isMeasured ? 'measured' : 'null_not_measured',
        'null_reason' => $isMeasured ? null : 'test: not measured',
        'observed_at' => $isMeasured ? $computedAt : null,
        'computed_at' => $computedAt,
        'source_ids' => '{'.implode(',', $sourceIds).'}',
        'adapter_id' => findOrInsertAdapter($subId),
        'adapter_version' => '1.0.0',
    ]);
}
