<?php

// Checks the MODULES_ENABLED rules of ADR-012 §3, which must match the Python runner's
// module_flags.py: not set → all on, a list → only those, empty → all off.
// Pure unit tests: no database, no Laravel app.

declare(strict_types=1);

use App\Engine\ModuleFlags;

test('every module is on when MODULES_ENABLED is not set', function () {
    $moduleFlags = new ModuleFlags(null);

    $isWaterEnabled = $moduleFlags->isModuleEnabled('water');

    expect($isWaterEnabled)->toBeTrue();
});

test('only the listed modules are on when MODULES_ENABLED is set', function () {
    $moduleFlags = new ModuleFlags('water,roads');

    expect($moduleFlags->isModuleEnabled('water'))->toBeTrue()
        ->and($moduleFlags->isModuleEnabled('roads'))->toBeTrue()
        ->and($moduleFlags->isModuleEnabled('land'))->toBeFalse();
});

test('every module is off when MODULES_ENABLED is empty', function () {
    $moduleFlags = new ModuleFlags('');

    $isWaterEnabled = $moduleFlags->isModuleEnabled('water');

    expect($isWaterEnabled)->toBeFalse();
});

test('spaces around a listed module name are ignored', function () {
    $moduleFlags = new ModuleFlags(' water , roads ');

    $isRoadsEnabled = $moduleFlags->isModuleEnabled('roads');

    expect($isRoadsEnabled)->toBeTrue();
});
