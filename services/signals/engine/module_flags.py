# Module on/off switches read from the MODULES_ENABLED environment variable (ADR-012 §3).
# The registry asks this before loading a module's adapters, so a module can be switched off
# on a server by changing one variable and restarting — no deploy (Bible §14.6).

from collections.abc import Mapping

MODULES_ENABLED_VARIABLE = "MODULES_ENABLED"


def is_module_enabled(module_name: str, environment: Mapping[str, str]) -> bool:
    """Return True when `module_name` may run, given the process environment.

    MODULES_ENABLED is a comma-separated list such as "water,roads". When it is not set at
    all, every module is on — the flag exists to switch modules off. When it is set, only the
    listed modules are on, so an empty value switches every module off.
    Implements ADR-012 §3 and Bible §14.6 — feature flags per module.
    """
    if MODULES_ENABLED_VARIABLE not in environment:
        return True
    listed_names = environment[MODULES_ENABLED_VARIABLE].split(",")
    enabled_modules = {name.strip() for name in listed_names}
    return module_name in enabled_modules
