<?php

// Builders for the rollup engine's inputs, shared by the Gates and VariableRollup unit tests.
// Required from tests/Pest.php.

declare(strict_types=1);

use App\Engine\SubVariableInput;

/**
 * The newest score for a sub-variable that an adapter measured.
 */
function measured(string $subId, float $score, float $confidence): SubVariableInput
{
    return new SubVariableInput($subId, $score, $confidence, "row-{$subId}");
}

/**
 * The newest score for a sub-variable that an adapter could not measure (null_not_measured).
 */
function notMeasured(string $subId): SubVariableInput
{
    return new SubVariableInput($subId, null, null, "row-{$subId}");
}

/**
 * Key inputs by sub_id, the shape VariableRollup::rollUp() takes.
 *
 * @param  list<SubVariableInput>  $inputs
 * @return array<string, SubVariableInput>
 */
function bySubId(array $inputs): array
{
    $inputsBySubId = [];
    foreach ($inputs as $input) {
        $inputsBySubId[$input->subId] = $input;
    }

    return $inputsBySubId;
}
