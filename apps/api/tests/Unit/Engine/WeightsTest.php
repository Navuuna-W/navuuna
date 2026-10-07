<?php

// Checks that Weights reads the real weights.yml and refuses a broken one, so the rollup never
// scores with weights nobody agreed to (Bible §6.7, CLAUDE.md §4).

declare(strict_types=1);

use App\Engine\InvalidWeightsFile;
use App\Engine\Weights;

/** Devyan's weights file, the one production reads. */
const REAL_WEIGHTS_FILE = __DIR__.'/../../../../../services/signals/engine/weights.yml';

/** A valid file body for V2–V5, so each test only has to write the part it breaks. */
const OTHER_VARIABLES_YAML = <<<'YAML'
V2_discrepancy: {"2.2": 1.0}
V3_momentum: {"3.1": 1.0}
V4_resource_security: {"4.1": 1.0}
V5_accessibility: {"5.1": 1.0}
YAML;

function writeWeightsFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'weights');
    file_put_contents($path, $content);

    return $path;
}

test('the real weights file loads with version 1 and the DEC-11 contributor counts', function () {
    $weights = Weights::fromFile(REAL_WEIGHTS_FILE);

    expect($weights->weightVersion)->toBe(1)
        ->and($weights->forVariable(1))->toHaveCount(4)
        ->and($weights->forVariable(2))->toHaveCount(4)
        ->and($weights->forVariable(3))->toHaveCount(6)
        ->and($weights->forVariable(4))->toHaveCount(6)
        ->and($weights->forVariable(5))->toHaveCount(6)
        ->and($weights->forVariable(1)['1.2'])->toBe(0.40);
});

test('gates and the guard carry no weight', function () {
    $weights = Weights::fromFile(REAL_WEIGHTS_FILE);

    expect($weights->forVariable(1))->not->toHaveKey('1.1')
        ->and($weights->forVariable(2))->not->toHaveKey('2.1')
        ->and($weights->forVariable(2))->not->toHaveKey('2.6');
});

test('a missing file is refused', function () {
    Weights::fromFile('/no/such/weights.yml');
})->throws(InvalidWeightsFile::class, 'not found');

test('a file that is not a YAML map is refused', function () {
    Weights::fromFile(writeWeightsFile('just a sentence'));
})->throws(InvalidWeightsFile::class, 'not a YAML map');

test('a file without weight_version is refused', function () {
    Weights::fromFile(writeWeightsFile("V1_activity: {\"1.2\": 1.0}\n".OTHER_VARIABLES_YAML));
})->throws(InvalidWeightsFile::class, 'weight_version');

test('a file missing a variable is refused', function () {
    Weights::fromFile(writeWeightsFile("weight_version: 1\n".OTHER_VARIABLES_YAML));
})->throws(InvalidWeightsFile::class, 'V1');

test('a variable with no weights is refused', function () {
    Weights::fromFile(writeWeightsFile("weight_version: 1\nV1_activity: {}\n".OTHER_VARIABLES_YAML));
})->throws(InvalidWeightsFile::class, 'must list');

test('a weight that is not a number is refused', function () {
    Weights::fromFile(writeWeightsFile("weight_version: 1\nV1_activity: {\"1.2\": high}\n".OTHER_VARIABLES_YAML));
})->throws(InvalidWeightsFile::class, 'must be a number');

test('weights that do not sum to 1.00 are refused', function () {
    Weights::fromFile(writeWeightsFile("weight_version: 1\nV1_activity: {\"1.2\": 0.5}\n".OTHER_VARIABLES_YAML));
})->throws(InvalidWeightsFile::class, 'sum to 1.00');
