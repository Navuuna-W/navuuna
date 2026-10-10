# Tests for the water 4.4 Competition adapter: every score rule and null reason in
# docs/modules/water.md "4.4 Competition", plus the registry finding the file.

from datetime import UTC, datetime
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreStatus
from modules.water.adapters.competition import SPEC, score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
EXTRACT_DATE = datetime(2026, 9, 20, tzinfo=UTC)
MAP_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
WELL_MAPPED_WARD_OTHERS = 20
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_entity(module: str = "water", has_map_source: bool = True) -> InputRow:
    metadata: InputRow = {}
    if has_map_source:
        metadata = {"source_id": str(MAP_SOURCE_ID), "observed_at": EXTRACT_DATE.isoformat()}
    return {"module": module, "metadata": metadata, "distance_m": 100.0}


def make_inputs(
    nearby_count: int,
    others_in_ward_count: int = WELL_MAPPED_WARD_OTHERS,
    other_nearby: list[InputRow] | None = None,
) -> AdapterInputs:
    nearby = [make_entity() for _ in range(nearby_count)] + (other_nearby or [])
    same_area = [make_entity() for _ in range(others_in_ward_count)]
    return AdapterInputs(
        as_of=AS_OF, nearby_entities=tuple(nearby), same_area_entities=tuple(same_area)
    )


def test_no_other_water_point_nearby_scores_zero_in_a_well_mapped_ward() -> None:
    inputs = make_inputs(nearby_count=0)

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert (result.value, result.unit, result.score) == (0.0, "water points", 0.0)
    assert result.confidence == 0.5


def test_four_water_points_nearby_score_forty() -> None:
    inputs = make_inputs(nearby_count=4)

    result = score(inputs)

    assert result.value == 4.0
    assert result.score == pytest.approx(40.0)


def test_ten_or_more_water_points_nearby_score_one_hundred() -> None:
    inputs = make_inputs(nearby_count=14)

    result = score(inputs)

    assert (result.value, result.score) == (14.0, 100.0)


def test_nearby_entities_of_other_modules_are_not_counted() -> None:
    inputs = make_inputs(nearby_count=2, other_nearby=[make_entity(module="roads")])

    result = score(inputs)

    assert result.value == 2.0


def test_result_traces_to_the_map_extract_once() -> None:
    inputs = make_inputs(nearby_count=3)

    result = score(inputs)

    assert result.source_ids == (MAP_SOURCE_ID,)
    assert result.observed_at == EXTRACT_DATE


def test_ward_with_fewer_than_five_water_points_is_not_measured_not_uncontested() -> None:
    inputs = make_inputs(nearby_count=0, others_in_ward_count=3)

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "Too few water points mapped in this ward to judge"
    assert result.score is None


def test_ward_with_exactly_five_water_points_counting_this_one_is_measured() -> None:
    inputs = make_inputs(nearby_count=1, others_in_ward_count=4)

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED


def test_counted_entities_without_recorded_map_source_are_not_measured() -> None:
    same_area = tuple(make_entity(has_map_source=False) for _ in range(WELL_MAPPED_WARD_OTHERS))
    inputs = AdapterInputs(as_of=AS_OF, nearby_entities=(), same_area_entities=same_area)

    result = score(inputs)

    assert result.null_reason == "Map source of nearby water points not recorded"


def test_adapter_asks_the_runner_for_entities_within_five_hundred_metres() -> None:
    assert SPEC.nearby_radius_m == 500


def test_registry_finds_the_competition_adapter_in_the_water_module() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    code_ref_by_sub_id = {adapter.spec.sub_id: adapter.code_ref for adapter in adapters}
    assert code_ref_by_sub_id["4.4"] == "modules/water/adapters/competition.py"
