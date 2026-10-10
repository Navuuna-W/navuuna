# Tests for the water 5.2 Connection quality adapter: every score row and null reason in
# docs/modules/water.md "5.2 Connection quality", plus the registry finding the file.

from datetime import UTC, datetime
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreStatus
from modules.water.adapters.connection_quality import SPEC, score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
EXTRACT_DATE = datetime(2026, 9, 20, tzinfo=UTC)
MAP_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_way(
    distance_m: float, surface: str | None = "asphalt", highway: str | None = "residential"
) -> InputRow:
    metadata: InputRow = {
        "source_id": str(MAP_SOURCE_ID),
        "observed_at": EXTRACT_DATE.isoformat(),
    }
    if surface is not None:
        metadata["surface"] = surface
    if highway is not None:
        metadata["highway"] = highway
    return {"distance_m": distance_m, "metadata": metadata}


def make_inputs(ways: list[InputRow]) -> AdapterInputs:
    return AdapterInputs(as_of=AS_OF, nearby_ways=tuple(ways))


@pytest.mark.parametrize(
    ("surface", "expected_score"),
    [
        ("paved", 100.0),
        ("asphalt", 100.0),
        ("concrete", 100.0),
        ("paving_stones", 75.0),
        ("compacted", 75.0),
        ("gravel", 60.0),
        ("fine_gravel", 60.0),
        ("unpaved", 30.0),
        ("dirt", 30.0),
        ("ground", 30.0),
        ("earth", 30.0),
        ("mud", 30.0),
    ],
)
def test_surface_of_the_nearest_way_sets_the_score(surface: str, expected_score: float) -> None:
    inputs = make_inputs([make_way(distance_m=12.0, surface=surface)])

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert result.value == f"{surface} (residential)"
    assert (result.score, result.confidence) == (expected_score, 0.6)


def test_result_traces_to_the_map_extract_of_the_way() -> None:
    inputs = make_inputs([make_way(distance_m=12.0)])

    result = score(inputs)

    assert result.source_ids == (MAP_SOURCE_ID,)
    assert result.observed_at == EXTRACT_DATE


def test_nearest_way_is_used_even_when_listed_last() -> None:
    inputs = make_inputs([make_way(150.0, surface="asphalt"), make_way(8.0, surface="dirt")])

    result = score(inputs)

    assert result.score == 30.0


def test_way_without_a_highway_tag_shows_the_surface_alone() -> None:
    inputs = make_inputs([make_way(distance_m=12.0, surface="gravel", highway=None)])

    result = score(inputs)

    assert result.value == "gravel"


def test_no_way_within_the_radius_is_not_measured() -> None:
    inputs = make_inputs([])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No mapped path within 200 m"
    assert result.score is None


def test_missing_surface_tag_is_not_measured_not_unpaved() -> None:
    inputs = make_inputs([make_way(distance_m=12.0, surface=None)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "Path surface not mapped in OSM"


def test_nearest_way_without_surface_is_not_replaced_by_a_farther_tagged_way() -> None:
    inputs = make_inputs([make_way(5.0, surface=None), make_way(90.0, surface="asphalt")])

    result = score(inputs)

    assert result.null_reason == "Path surface not mapped in OSM"


def test_unknown_surface_value_is_not_measured() -> None:
    inputs = make_inputs([make_way(distance_m=12.0, surface="cobblestone:flattened")])

    result = score(inputs)

    assert result.null_reason == "Path surface value not recognised"


def test_way_without_recorded_map_source_is_not_measured_not_guessed() -> None:
    way = make_way(distance_m=12.0)
    way["metadata"] = {"surface": "asphalt"}
    inputs = make_inputs([way])

    result = score(inputs)

    assert result.null_reason == "Map source of this path not recorded"


def test_adapter_asks_the_runner_for_ways_within_two_hundred_metres() -> None:
    assert SPEC.nearby_radius_m == 200


def test_registry_finds_the_connection_quality_adapter_in_the_water_module() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    code_ref_by_sub_id = {adapter.spec.sub_id: adapter.code_ref for adapter in adapters}
    assert code_ref_by_sub_id["5.2"] == "modules/water/adapters/connection_quality.py"
