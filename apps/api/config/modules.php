<?php

// Module on/off switches for the Laravel side (ADR-012 §3, built in K-08). The Python runner
// reads the same MODULES_ENABLED variable (services/signals/engine/module_flags.py), so a module
// is switched off everywhere by changing one variable and restarting — no deploy (Bible §14.6).

declare(strict_types=1);

return [

    // Comma-separated module names, e.g. "water,roads". Null (variable not set) means every
    // module is on; an empty string means every module is off. Read through App\Engine\ModuleFlags.
    'enabled' => env('MODULES_ENABLED'),

];
