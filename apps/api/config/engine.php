<?php

// Settings for the rollup engine (app/Engine, K-10) and the findings engine (app/Findings, K-13):
// the few things that differ between machines, such as where Devyan's files live.

declare(strict_types=1);

return [

    // Devyan's weights file (Bible §6.7). By default the copy in this monorepo, two levels up
    // from apps/api. Set ENGINE_WEIGHTS_PATH when the app is deployed without the full repo.
    'weights_path' => env(
        'ENGINE_WEIGHTS_PATH',
        base_path('../../services/signals/engine/weights.yml'),
    ),

    // The modules folder of the signal service. The findings engine (K-13) reads
    // {modules_path}/{entity module}/findings.yml, Devyan's narrative templates (D-20).
    'modules_path' => env(
        'ENGINE_MODULES_PATH',
        base_path('../../services/signals/modules'),
    ),

];
