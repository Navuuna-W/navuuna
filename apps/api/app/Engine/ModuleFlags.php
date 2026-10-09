<?php

// Answers "is this module switched on?" from config('modules.enabled'), the MODULES_ENABLED
// variable. Same rules as the Python runner's module_flags.py, so both sides always agree.

declare(strict_types=1);

namespace App\Engine;

/**
 * Implements ADR-012 §3 and Bible §14.6 — feature flags per module.
 */
final class ModuleFlags
{
    /**
     * @param  string|null  $enabledModules  MODULES_ENABLED: null when not set, else e.g. "water,roads"
     */
    public function __construct(private readonly ?string $enabledModules) {}

    /**
     * True when the module may run. Not set at all → every module is on (the flag exists to switch
     * modules off). Set → only the listed modules are on, so an empty value switches all off.
     */
    public function isModuleEnabled(string $module): bool
    {
        if ($this->enabledModules === null) {
            return true;
        }

        $listedModules = array_map(trim(...), explode(',', $this->enabledModules));

        return in_array($module, $listedModules, true);
    }
}
