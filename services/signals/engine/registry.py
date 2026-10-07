# Finds every adapter under modules/*/adapters/ and checks they fit together (ADR-003, FR-06).
# Runs first in every signal run: the runner gets the list back in dependency order, so an
# adapter always runs after the adapters whose results it reads (2.1 after 1.1).

import sys
from collections.abc import Mapping
from importlib.machinery import SourceFileLoader
from pathlib import Path
from types import ModuleType
from typing import cast

from engine.adapter_spec import AdapterSpec
from engine.module_flags import is_module_enabled
from engine.registered_adapter import RegisteredAdapter, ScoreFunction

# What every adapter file must define (see services/signals/README.md).
SPEC_NAME = "SPEC"
SCORE_FUNCTION_NAME = "score"
ADAPTERS_FOLDER_NAME = "adapters"
# Files starting with "_" (such as __init__.py or _shared.py) are helpers, not adapters.
HELPER_FILE_PREFIX = "_"


class AdapterRegistryError(Exception):
    """An adapter file is broken or does not fit with the others. The run stops."""


def discover_adapters(
    modules_root: Path, environment: Mapping[str, str]
) -> list[RegisteredAdapter]:
    """Load every adapter of every enabled module, checked and in dependency order.

    Inputs: the modules folder (services/signals/modules in production, a fixture folder in
    tests) and the process environment (for MODULES_ENABLED). Output: the adapters, each one
    after every adapter it depends on. Raises AdapterRegistryError if any file is broken, two
    adapters score the same sub-variable, a dependency has no adapter, or dependencies loop.
    Implements ADR-003, ADR-012 §3 and Bible §6.8.
    """
    adapter_files = find_adapter_files(modules_root, environment)
    adapters = [load_adapter_file(path, modules_root) for path in adapter_files]
    check_sub_ids_are_unique(adapters)
    check_dependencies_are_registered(adapters)
    return order_by_dependencies(adapters)


def find_adapter_files(modules_root: Path, environment: Mapping[str, str]) -> list[Path]:
    """Return the adapter files of every enabled module, sorted so runs are repeatable."""
    adapter_files: list[Path] = []
    for module_folder in sorted(modules_root.iterdir()):
        if not module_folder.is_dir():
            continue
        if not is_module_enabled(module_folder.name, environment):
            continue
        for path in sorted((module_folder / ADAPTERS_FOLDER_NAME).glob("*.py")):
            if not path.name.startswith(HELPER_FILE_PREFIX):
                adapter_files.append(path)
    return adapter_files


def load_adapter_file(path: Path, modules_root: Path) -> RegisteredAdapter:
    """Import one adapter file and check it defines SPEC and score for its own module.

    The module check matters: a file in modules/water/ that claimed module "roads" would
    escape the water module's on/off switch.
    """
    code_ref = path.relative_to(modules_root.parent).as_posix()
    module_folder_name = path.parent.parent.name
    python_module = import_python_file(path, module_folder_name)

    spec = getattr(python_module, SPEC_NAME, None)
    if not isinstance(spec, AdapterSpec):
        raise AdapterRegistryError(f"{code_ref} must define {SPEC_NAME} = AdapterSpec(...)")
    score_function = getattr(python_module, SCORE_FUNCTION_NAME, None)
    if not callable(score_function):
        raise AdapterRegistryError(f"{code_ref} must define a {SCORE_FUNCTION_NAME}() function")
    if spec.module != module_folder_name:
        raise AdapterRegistryError(
            f"{code_ref} says module {spec.module!r} but lives in {module_folder_name!r}"
        )
    # callable() cannot check the signature; the runner validates what score() returns.
    return RegisteredAdapter(
        spec=spec, score=cast(ScoreFunction, score_function), code_ref=code_ref
    )


def import_python_file(path: Path, module_folder_name: str) -> ModuleType:
    """Import a .py file by its path, under a name unique to its module and file."""
    import_name = f"navuuna_adapters.{module_folder_name}.{path.stem}"
    loader = SourceFileLoader(import_name, str(path))
    python_module = ModuleType(import_name)
    python_module.__file__ = str(path)
    # Registered before running so Pydantic models defined in the file can resolve their types.
    sys.modules[import_name] = python_module
    loader.exec_module(python_module)
    return python_module


def check_sub_ids_are_unique(adapters: list[RegisteredAdapter]) -> None:
    """Raise if two adapters score the same sub-variable (core.adapters: one per sub_id)."""
    code_ref_by_sub_id: dict[str, str] = {}
    for adapter in adapters:
        sub_id = adapter.spec.sub_id
        if sub_id in code_ref_by_sub_id:
            raise AdapterRegistryError(
                f"sub-variable {sub_id} is scored by both "
                f"{code_ref_by_sub_id[sub_id]} and {adapter.code_ref}"
            )
        code_ref_by_sub_id[sub_id] = adapter.code_ref


def check_dependencies_are_registered(adapters: list[RegisteredAdapter]) -> None:
    """Raise if an adapter reads a sub-variable that no enabled adapter scores."""
    registered_sub_ids = {adapter.spec.sub_id for adapter in adapters}
    for adapter in adapters:
        missing_sub_ids = sorted(adapter.spec.depends_on_sub_ids - registered_sub_ids)
        if missing_sub_ids:
            raise AdapterRegistryError(
                f"{adapter.code_ref} depends on {missing_sub_ids}, which no enabled adapter scores"
            )


def order_by_dependencies(adapters: list[RegisteredAdapter]) -> list[RegisteredAdapter]:
    """Return the adapters so each comes after every adapter it depends on.

    Works in rounds: each round places every adapter whose dependencies are all placed.
    A round that places nothing means the remaining adapters depend on each other in a loop.
    """
    ordered_adapters: list[RegisteredAdapter] = []
    placed_sub_ids: set[str] = set()
    waiting_adapters = sorted(adapters, key=lambda adapter: adapter.spec.sub_id)
    while waiting_adapters:
        ready_adapters = [
            adapter
            for adapter in waiting_adapters
            if adapter.spec.depends_on_sub_ids <= placed_sub_ids
        ]
        if not ready_adapters:
            looping_files = [adapter.code_ref for adapter in waiting_adapters]
            raise AdapterRegistryError(f"these adapters depend on each other: {looping_files}")
        ordered_adapters.extend(ready_adapters)
        placed_sub_ids.update(adapter.spec.sub_id for adapter in ready_adapters)
        waiting_adapters = [
            adapter for adapter in waiting_adapters if adapter not in ready_adapters
        ]
    return ordered_adapters
