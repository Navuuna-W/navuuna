# Tests for the water 2.1 Existence gap adapter: every rule and null reason in
# docs/modules/water.md "2.1 Existence gap", plus the registry running it after 1.1.

from datetime import UTC, datetime, timedelta
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreResult, ScoreStatus
from modules.water.adapters.existence_gap import score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
PRESENCE_OBSERVED_AT = datetime(2026, 9, 20, tzinfo=UTC)
PRESENCE_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000002")
OLDER_DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000003")
PRESENCE_CONFIDENCE = 0.7
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_record(days_old: int | None, document_id: UUID = DOCUMENT_ID) -> InputRow:
    record_date = None
    if days_old is not None:
        record_date = (AS_OF - timedelta(days=days_old)).date().isoformat()
    return {"document_id": str(document_id), "record_date": record_date}


def make_presence(value: str) -> ScoreResult:
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=value,
        score=100.0 if value == "present" else 0.0,
        confidence=PRESENCE_CONFIDENCE,
        observed_at=PRESENCE_OBSERVED_AT,
        source_ids=(PRESENCE_SOURCE_ID,),
    )


def make_inputs(records: list[InputRow], presence: ScoreResult | None) -> AdapterInputs:
    earlier_results = {} if presence is None else {"1.1": presence}
    return AdapterInputs(as_of=AS_OF, records=tuple(records), sub_variable_results=earlier_results)


def test_record_for_an_absent_water_point_is_a_gap() -> None:
    inputs = make_inputs([make_record(days_old=0)], make_presence("absent"))

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert (result.value, result.score) == ("gap", 100.0)
    assert result.confidence == pytest.approx(PRESENCE_CONFIDENCE)


def test_record_for_a_present_water_point_is_no_gap() -> None:
    inputs = make_inputs([make_record(days_old=0)], make_presence("present"))

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert (result.value, result.score) == ("no_gap", 0.0)


def test_result_traces_to_the_document_and_the_presence_sources() -> None:
    inputs = make_inputs([make_record(days_old=400)], make_presence("absent"))

    result = score(inputs)

    assert result.source_ids == (DOCUMENT_ID, PRESENCE_SOURCE_ID)
    assert result.observed_at == PRESENCE_OBSERVED_AT


def test_record_newer_than_the_presence_check_sets_observed_at() -> None:
    inputs = make_inputs([make_record(days_old=1)], make_presence("absent"))

    result = score(inputs)

    assert result.observed_at == datetime(2026, 10, 7, tzinfo=UTC)


def test_one_year_old_record_halves_the_confidence() -> None:
    inputs = make_inputs([make_record(days_old=365)], make_presence("absent"))

    result = score(inputs)

    assert result.confidence == pytest.approx(PRESENCE_CONFIDENCE * 0.5)


def test_very_old_record_keeps_the_minimum_freshness_factor() -> None:
    inputs = make_inputs([make_record(days_old=3000)], make_presence("absent"))

    result = score(inputs)

    assert result.confidence == pytest.approx(PRESENCE_CONFIDENCE * 0.3)


def test_record_dated_after_as_of_does_not_raise_confidence() -> None:
    inputs = make_inputs([make_record(days_old=-30)], make_presence("absent"))

    result = score(inputs)

    assert result.confidence == pytest.approx(PRESENCE_CONFIDENCE)


def test_undated_record_is_trusted_as_little_as_the_oldest_record() -> None:
    inputs = make_inputs([make_record(days_old=None)], make_presence("absent"))

    result = score(inputs)

    assert result.value == "gap"
    assert result.confidence == pytest.approx(PRESENCE_CONFIDENCE * 0.3)
    assert result.observed_at == PRESENCE_OBSERVED_AT


def test_most_recent_record_sets_confidence_and_older_ones_stay_as_sources() -> None:
    older_record = make_record(days_old=3000, document_id=OLDER_DOCUMENT_ID)
    newest_record = make_record(days_old=0)
    inputs = make_inputs([older_record, newest_record], make_presence("absent"))

    result = score(inputs)

    assert result.confidence == pytest.approx(PRESENCE_CONFIDENCE)
    assert result.source_ids == (DOCUMENT_ID, OLDER_DOCUMENT_ID, PRESENCE_SOURCE_ID)


def test_entity_without_a_matched_record_is_not_measured() -> None:
    inputs = make_inputs([], make_presence("absent"))

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No official record found"
    assert result.score is None


def test_unmeasured_presence_is_not_measured_not_a_gap() -> None:
    presence = ScoreResult(
        status=ScoreStatus.NULL_NOT_MEASURED,
        null_reason="No ground observation of this water point yet",
    )
    inputs = make_inputs([make_record(days_old=0)], presence)

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "Presence could not be checked"


def test_missing_presence_result_is_not_measured() -> None:
    inputs = make_inputs([make_record(days_old=0)], None)

    result = score(inputs)

    assert result.null_reason == "Presence could not be checked"


def test_registry_runs_the_existence_gap_adapter_after_presence() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    sub_ids_in_run_order = [adapter.spec.sub_id for adapter in adapters]
    assert sub_ids_in_run_order.index("1.1") < sub_ids_in_run_order.index("2.1")
