# Tests for AdapterSpec, the label every adapter carries (ADR-003, Bible §6.8).
# The examples use real water specs from docs/modules/water.md so they double as usage docs.

from typing import Any

import pytest
from pydantic import ValidationError

from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind


def existence_gap_spec_fields(**overrides: Any) -> dict[str, Any]:
    # 2.1 Existence gap reads the matched record and the entity's 1.1 Presence result.
    fields: dict[str, Any] = {
        "module": "water",
        "sub_id": "2.1",
        "entity_types": [EntityType.POINT],
        "version": "1.0.0",
        "requires": [InputKind.ENTITY, InputKind.RECORDS],
        "depends_on_sub_ids": ["1.1"],
        "signal_description": "Whether a matched official record has no observed counterpart.",
    }
    fields.update(overrides)
    return fields


def test_a_complete_spec_is_accepted() -> None:
    spec = AdapterSpec(**existence_gap_spec_fields())

    assert spec.sub_id == "2.1"
    assert spec.entity_types == frozenset({EntityType.POINT})
    assert spec.depends_on_sub_ids == frozenset({"1.1"})


def test_a_spec_without_dependencies_is_accepted() -> None:
    spec = AdapterSpec(**existence_gap_spec_fields(sub_id="1.1", depends_on_sub_ids=[]))

    assert spec.depends_on_sub_ids == frozenset()


def test_entity_types_written_as_plain_strings_are_accepted() -> None:
    spec = AdapterSpec(**existence_gap_spec_fields(entity_types=["point", "area"]))

    assert spec.entity_types == frozenset({EntityType.POINT, EntityType.AREA})


@pytest.mark.parametrize("sub_id", ["6.1", "1.7", "1", ""])
def test_an_invented_sub_variable_is_rejected(sub_id: str) -> None:
    with pytest.raises(ValidationError, match="not one of the sub-variables"):
        AdapterSpec(**existence_gap_spec_fields(sub_id=sub_id))


def test_an_unknown_dependency_is_rejected() -> None:
    with pytest.raises(ValidationError, match="unknown sub-variables"):
        AdapterSpec(**existence_gap_spec_fields(depends_on_sub_ids=["9.9"]))


def test_an_adapter_cannot_depend_on_its_own_result() -> None:
    with pytest.raises(ValidationError, match="cannot depend on its own result"):
        AdapterSpec(**existence_gap_spec_fields(depends_on_sub_ids=["2.1"]))


@pytest.mark.parametrize("empty_field", ["entity_types", "requires"])
def test_a_spec_must_name_at_least_one_entity_type_and_input(empty_field: str) -> None:
    with pytest.raises(ValidationError):
        AdapterSpec(**existence_gap_spec_fields(**{empty_field: []}))


def test_an_unknown_entity_type_is_rejected() -> None:
    with pytest.raises(ValidationError):
        AdapterSpec(**existence_gap_spec_fields(entity_types=["site"]))


def test_an_unknown_input_kind_is_rejected() -> None:
    with pytest.raises(ValidationError):
        AdapterSpec(**existence_gap_spec_fields(requires=["database"]))


@pytest.mark.parametrize("version", ["1", "1.0", "v1.0.0", "1.0.0-beta"])
def test_a_version_that_is_not_major_minor_patch_is_rejected(version: str) -> None:
    with pytest.raises(ValidationError):
        AdapterSpec(**existence_gap_spec_fields(version=version))


@pytest.mark.parametrize("module", ["Water", "water-points", "", "1water"])
def test_a_module_name_that_cannot_be_a_folder_name_is_rejected(module: str) -> None:
    with pytest.raises(ValidationError):
        AdapterSpec(**existence_gap_spec_fields(module=module))


@pytest.mark.parametrize("signal_description", ["", "   "])
def test_a_blank_signal_description_is_rejected(signal_description: str) -> None:
    with pytest.raises(ValidationError):
        AdapterSpec(**existence_gap_spec_fields(signal_description=signal_description))
