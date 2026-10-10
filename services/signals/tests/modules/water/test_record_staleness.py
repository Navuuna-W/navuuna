# Tests for the water 2.5 Record staleness adapter: every score rule and null reason in
# docs/modules/water.md "2.5 Record staleness", plus the registry finding the file.

from datetime import UTC, datetime, timedelta
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreStatus
from modules.water.adapters.record_staleness import score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
MAP_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000002")
OLDER_DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000003")
REPORT_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000004")
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_record(days_old: int | None, document_id: UUID = DOCUMENT_ID) -> InputRow:
    record_date = None
    if days_old is not None:
        record_date = (AS_OF - timedelta(days=days_old)).date().isoformat()
    return {"document_id": str(document_id), "record_date": record_date}


def make_observation(days_ago: int) -> InputRow:
    return {
        "source_id": str(REPORT_SOURCE_ID),
        "observed_at": (AS_OF - timedelta(days=days_ago)).isoformat(),
    }


def make_entity(extract_days_ago: int | None) -> InputRow:
    if extract_days_ago is None:
        return {"metadata": {}}
    extract_date = AS_OF - timedelta(days=extract_days_ago)
    return {"metadata": {"source_id": str(MAP_SOURCE_ID), "observed_at": extract_date.isoformat()}}


def make_inputs(
    records: list[InputRow],
    observations: list[InputRow] | None = None,
    extract_days_ago: int | None = None,
) -> AdapterInputs:
    return AdapterInputs(
        as_of=AS_OF,
        entity=make_entity(extract_days_ago),
        observations=tuple(observations or []),
        records=tuple(records),
    )


def test_record_one_year_behind_the_newest_observation_scores_fifty() -> None:
    inputs = make_inputs([make_record(days_old=375)], [make_observation(days_ago=10)])

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert (result.value, result.unit) == (365.0, "days")
    assert result.score == pytest.approx(50.0)
    assert result.confidence == 0.9


def test_record_two_years_or_more_behind_scores_one_hundred() -> None:
    inputs = make_inputs([make_record(days_old=2000)], [make_observation(days_ago=0)])

    result = score(inputs)

    assert (result.value, result.score) == (2000.0, 100.0)


def test_record_newer_than_every_observation_scores_zero() -> None:
    inputs = make_inputs([make_record(days_old=5)], [make_observation(days_ago=60)])

    result = score(inputs)

    assert (result.value, result.score) == (0.0, 0.0)
    assert result.observed_at == datetime(2026, 10, 3, tzinfo=UTC)


def test_newest_of_several_observations_is_the_one_compared() -> None:
    observations = [make_observation(days_ago=300), make_observation(days_ago=20)]
    inputs = make_inputs([make_record(days_old=120)], observations)

    result = score(inputs)

    assert result.value == 100.0
    assert result.observed_at == AS_OF - timedelta(days=20)


def test_map_extract_date_counts_as_an_observation() -> None:
    inputs = make_inputs([make_record(days_old=100)], extract_days_ago=27)

    result = score(inputs)

    assert result.value == 73.0
    assert result.source_ids == (DOCUMENT_ID, MAP_SOURCE_ID)


def test_observation_dated_after_as_of_is_ignored() -> None:
    observations = [make_observation(days_ago=-5), make_observation(days_ago=30)]
    inputs = make_inputs([make_record(days_old=130)], observations)

    result = score(inputs)

    assert result.value == 100.0


def test_most_recent_record_is_compared_and_older_ones_stay_as_sources() -> None:
    older_record = make_record(days_old=900, document_id=OLDER_DOCUMENT_ID)
    inputs = make_inputs([older_record, make_record(days_old=50)], [make_observation(days_ago=0)])

    result = score(inputs)

    assert result.value == 50.0
    assert result.source_ids == (DOCUMENT_ID, OLDER_DOCUMENT_ID, REPORT_SOURCE_ID)


def test_entity_without_a_matched_record_is_not_measured() -> None:
    inputs = make_inputs([], [make_observation(days_ago=0)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No official record found"
    assert result.score is None


def test_record_without_a_date_is_not_measured_not_fully_stale() -> None:
    inputs = make_inputs([make_record(days_old=None)], [make_observation(days_ago=0)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No inspection date in the register"


def test_no_observation_and_no_map_extract_date_is_not_measured() -> None:
    inputs = make_inputs([make_record(days_old=100)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No observation to compare the record with"


def test_registry_finds_the_record_staleness_adapter_in_the_water_module() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    code_ref_by_sub_id = {adapter.spec.sub_id: adapter.code_ref for adapter in adapters}
    assert code_ref_by_sub_id["2.5"] == "modules/water/adapters/record_staleness.py"
