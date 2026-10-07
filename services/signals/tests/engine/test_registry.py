# Tests for the adapter registry (ADR-003, ADR-012 §3). Good cases use the fake modules in
# tests/fixtures/modules; broken cases write small adapter files into a temporary folder.

from pathlib import Path

import pytest

from engine.registry import AdapterRegistryError, discover_adapters

FIXTURE_MODULES_ROOT = Path(__file__).parent.parent / "fixtures" / "modules"
ALL_MODULES_ENABLED: dict[str, str] = {}


def write_adapter_file(
    modules_root: Path,
    module: str,
    file_name: str,
    sub_id: str,
    depends_on: str = "",
    body: str | None = None,
) -> None:
    """Write a minimal adapter file. `body` replaces the whole file when given."""
    adapters_folder = modules_root / module / "adapters"
    adapters_folder.mkdir(parents=True, exist_ok=True)
    dependencies = f"[{depends_on!r}]" if depends_on else "[]"
    default_body = f"""
from engine.adapter_spec import AdapterSpec
from engine.score_result import ScoreResult, ScoreStatus

SPEC = AdapterSpec(
    module={module!r},
    sub_id={sub_id!r},
    entity_types=["point"],
    version="1.0.0",
    requires=["entity"],
    depends_on_sub_ids={dependencies},
    signal_description="Test adapter.",
)


def score(inputs):
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="Test")
"""
    (adapters_folder / file_name).write_text(default_body if body is None else body)


def test_adapters_of_every_enabled_module_are_found() -> None:
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, ALL_MODULES_ENABLED)

    assert [adapter.spec.sub_id for adapter in adapters] == ["1.1", "5.2", "2.1"]


def test_an_adapter_runs_after_the_adapter_it_depends_on() -> None:
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, ALL_MODULES_ENABLED)

    sub_ids = [adapter.spec.sub_id for adapter in adapters]
    assert sub_ids.index("1.1") < sub_ids.index("2.1")


def test_code_ref_is_the_path_from_the_service_root() -> None:
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, ALL_MODULES_ENABLED)

    assert adapters[0].code_ref == "modules/fake/adapters/presence.py"


def test_the_score_function_of_the_file_is_registered() -> None:
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, ALL_MODULES_ENABLED)

    assert adapters[0].score.__name__ == "score"
    assert adapters[0].score.__module__ == "navuuna_adapters.fake.presence"


def test_a_switched_off_module_is_not_loaded() -> None:
    environment = {"MODULES_ENABLED": "other"}

    adapters = discover_adapters(FIXTURE_MODULES_ROOT, environment)

    assert [adapter.spec.module for adapter in adapters] == ["other"]


def test_helper_files_and_loose_files_are_skipped(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "presence.py", "1.1")
    write_adapter_file(tmp_path, "water", "_shared.py", "1.2", body="SHARED_VALUE = 1\n")
    (tmp_path / "README.md").write_text("Not a module.")

    adapters = discover_adapters(tmp_path, ALL_MODULES_ENABLED)

    assert [adapter.code_ref for adapter in adapters] == [
        f"{tmp_path.name}/water/adapters/presence.py"
    ]


def test_a_file_without_a_spec_is_rejected(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "broken.py", "1.1", body="def score(inputs): ...\n")

    with pytest.raises(AdapterRegistryError, match="must define SPEC"):
        discover_adapters(tmp_path, ALL_MODULES_ENABLED)


def test_a_file_without_a_score_function_is_rejected(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "presence.py", "1.1")
    adapter_file = tmp_path / "water" / "adapters" / "presence.py"
    adapter_file.write_text(adapter_file.read_text().replace("def score(", "def compute("))

    with pytest.raises(AdapterRegistryError, match="must define a score"):
        discover_adapters(tmp_path, ALL_MODULES_ENABLED)


def test_a_spec_naming_another_module_is_rejected(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "presence.py", "1.1")
    adapter_file = tmp_path / "water" / "adapters" / "presence.py"
    adapter_file.write_text(adapter_file.read_text().replace("module='water'", "module='roads'"))

    with pytest.raises(AdapterRegistryError, match="says module 'roads' but lives in 'water'"):
        discover_adapters(tmp_path, ALL_MODULES_ENABLED)


def test_two_adapters_for_one_sub_variable_are_rejected(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "presence.py", "1.1")
    write_adapter_file(tmp_path, "roads", "presence.py", "1.1")

    with pytest.raises(AdapterRegistryError, match="1.1 is scored by both"):
        discover_adapters(tmp_path, ALL_MODULES_ENABLED)


def test_a_dependency_without_an_adapter_is_rejected(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "existence_gap.py", "2.1", depends_on="1.1")

    with pytest.raises(AdapterRegistryError, match="no enabled adapter scores"):
        discover_adapters(tmp_path, ALL_MODULES_ENABLED)


def test_adapters_that_depend_on_each_other_are_rejected(tmp_path: Path) -> None:
    write_adapter_file(tmp_path, "water", "presence.py", "1.1", depends_on="1.2")
    write_adapter_file(tmp_path, "water", "operational_state.py", "1.2", depends_on="1.1")

    with pytest.raises(AdapterRegistryError, match="depend on each other"):
        discover_adapters(tmp_path, ALL_MODULES_ENABLED)
