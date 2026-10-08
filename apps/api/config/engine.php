<?php

// Settings for the rollup engine (app/Engine, K-10). The engine turns sub-variable scores into
// the five variable scores; these are the few things that differ between machines.

declare(strict_types=1);

return [

    // Devyan's weights file (Bible §6.7). By default the copy in this monorepo, two levels up
    // from apps/api. Set ENGINE_WEIGHTS_PATH when the app is deployed without the full repo.
    'weights_path' => env(
        'ENGINE_WEIGHTS_PATH',
        base_path('../../services/signals/engine/weights.yml'),
    ),

];
