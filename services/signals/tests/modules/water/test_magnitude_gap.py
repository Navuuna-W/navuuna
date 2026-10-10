# Tests for the water 2.2 Magnitude gap adapter: every score rule and null reason in
# docs/modules/water.md "2.2 Magnitude gap", plus the registry finding the file.

from datetime import UTC, datetime, timedelta
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreStatus
from modules.water.adapters.magnitude_gap import score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000002")
OLDER_DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000003")
ALIGNMENT_CONFIDENCE = 0.8
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_record(
    rated_yield_m3d: float | None = 100.0,
    reported_production_m3d: float | None = 40.0,
    days_old: int | None = 0,
    document_id: UUID = DOCUMENT_ID,
) -> InputRow:
    record_date = None
    if days_old is not None:
        record_date = (AS_OF - timedelta(days=days_old)).date().isoformat()
    return {
        "document_id": str(document_id),
        "record_date": record_date,
        "rated_yield_m3d": rated_yield_m3d,
        "reported_production_m3d": reported_production_m3d,
        "alignment_confidence": ALIGNMENT_CONFIDENCE,
    }


def make_inputs(records: list[InputRow]) -> AdapterInputs:
    return AdapterInputs(as_of=AS_OF, records=tuple(records))


def test_production_below_rated_yield_scores_the_missing_share() -> None:
    inputs = make_inputs([make_record(rated_yield_m3d=100.0, reported_production_m3d=40.0)])

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert result.value == pytest.approx(0.6)
    assert result.score == pytest.approx(60.0)
    assert result.confidence == pytest.approx(ALIGNMENT_CONFIDENCE)


def test_producing_the_rated_yield_scores_zero() -> None:
    inputs = make_inputs([make_record(rated_yield_m3d=100.0, reported_production_m3d=100.0)])

    result = score(inputs)

    assert (result.value, result.score) == (0.0, 0.0)


def test_producing_nothing_scores_one_hundred() -> None:
    inputs = make_inputs([make_record(rated_yield_m3d=100.0, reported_production_m3d=0.0)])

    result = score(inputs)

    assert (result.value, result.score) == (1.0, 100.0)


def test_producing_more_than_rated_keeps_a_negative_value_but_scores_zero() -> None:
    inputs = make_inputs([make_record(rated_yield_m3d=100.0, reported_production_m3d=150.0)])

    result = score(inputs)

    assert result.value == pytest.approx(-0.5)
    assert result.score == 0.0


def test_result_traces_to_the_document_and_its_record_date() -> None:
    inputs = make_inputs([make_record(days_old=10)])

    result = score(inputs)

    assert result.source_ids == (DOCUMENT_ID,)
    assert result.observed_at == datetime(2026, 9, 28, tzinfo=UTC)


def test_one_year_old_record_halves_the_confidence() -> None:
    inputs = make_inputs([make_record(days_old=365)])

    result = score(inputs)

    assert result.confidence == pytest.approx(ALIGNMENT_CONFIDENCE * 0.5)


def test_very_old_record_keeps_the_minimum_freshness_factor() -> None:
    inputs = make_inputs([make_record(days_old=3000)])

    result = score(inputs)

    assert result.confidence == pytest.approx(ALIGNMENT_CONFIDENCE * 0.3)


def test_most_recent_record_feeds_the_score_and_older_ones_stay_as_sources() -> None:
    older_record = make_record(
        reported_production_m3d=0.0, days_old=900, document_id=OLDER_DOCUMENT_ID
    )
    newest_record = make_record(reported_production_m3d=100.0, days_old=0)
    inputs = make_inputs([older_record, newest_record])

    result = score(inputs)

    assert result.score == 0.0
    assert result.source_ids == (DOCUMENT_ID, OLDER_DOCUMENT_ID)


def test_entity_without_a_matched_record_is_not_measured() -> None:
    inputs = make_inputs([])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No official record found"
    assert result.score is None


def test_record_without_a_date_is_not_measured() -> None:
    inputs = make_inputs([make_record(days_old=None)])

    result = score(inputs)

    assert result.null_reason == "No inspection date in the register"


def test_missing_rated_yield_is_not_measured_not_zero() -> None:
    inputs = make_inputs([make_record(rated_yield_m3d=None)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No rated yield in the register"


def test_missing_production_figure_is_not_measured_not_a_full_gap() -> None:
    inputs = make_inputs([make_record(reported_production_m3d=None)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No production figure in the register"


def test_zero_rated_yield_is_not_measured() -> None:
    inputs = make_inputs([make_record(rated_yield_m3d=0.0)])

    result = score(inputs)

    assert result.null_reason == "Rated yield in the register is not a positive number"


def test_registry_finds_the_magnitude_gap_adapter_in_the_water_module() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    code_ref_by_sub_id = {adapter.spec.sub_id: adapter.code_ref for adapter in adapters}
    assert code_ref_by_sub_id["2.2"] == "modules/water/adapters/magnitude_gap.py"
