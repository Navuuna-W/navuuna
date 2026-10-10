# Tests for the water 2.4 Status gap adapter: every score rule and null reason in
# docs/modules/water.md "2.4 Status gap", plus the registry running it after 1.2.

from datetime import UTC, datetime, timedelta
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreResult, ScoreStatus
from modules.water.adapters.status_gap import score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
STATE_OBSERVED_AT = datetime(2026, 9, 20, tzinfo=UTC)
REPORT_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000002")
ALIGNMENT_CONFIDENCE = 0.9
STATE_CONFIDENCE = 0.8
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_record(status_declared: str | None, days_old: int | None = 0) -> InputRow:
    record_date = None
    if days_old is not None:
        record_date = (AS_OF - timedelta(days=days_old)).date().isoformat()
    return {
        "document_id": str(DOCUMENT_ID),
        "record_date": record_date,
        "status_declared": status_declared,
        "alignment_confidence": ALIGNMENT_CONFIDENCE,
    }


def make_observed_state(
    value: str, source_ids: tuple[UUID, ...] = (REPORT_SOURCE_ID,)
) -> ScoreResult:
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=value,
        score={"yes": 100.0, "intermittent": 50.0, "no": 0.0}[value],
        confidence=STATE_CONFIDENCE,
        observed_at=STATE_OBSERVED_AT,
        source_ids=source_ids,
    )


def make_inputs(records: list[InputRow], observed_state: ScoreResult | None) -> AdapterInputs:
    earlier_results = {} if observed_state is None else {"1.2": observed_state}
    return AdapterInputs(as_of=AS_OF, records=tuple(records), sub_variable_results=earlier_results)


@pytest.mark.parametrize(
    ("observed_value", "expected_score"), [("yes", 0.0), ("intermittent", 50.0), ("no", 100.0)]
)
def test_declared_operational_scores_by_the_observed_state(
    observed_value: str, expected_score: float
) -> None:
    inputs = make_inputs([make_record("operational")], make_observed_state(observed_value))

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert result.value == f"operational → {observed_value}"
    assert result.score == expected_score


@pytest.mark.parametrize("observed_value", ["yes", "intermittent", "no"])
def test_declared_not_operational_is_never_a_gap(observed_value: str) -> None:
    inputs = make_inputs([make_record("not_operational")], make_observed_state(observed_value))

    result = score(inputs)

    assert result.value == f"not_operational → {observed_value}"
    assert result.score == 0.0


def test_confidence_is_the_weaker_of_the_record_and_the_observed_state() -> None:
    inputs = make_inputs([make_record("operational", days_old=0)], make_observed_state("no"))

    result = score(inputs)

    assert result.confidence == pytest.approx(STATE_CONFIDENCE)


def test_one_year_old_record_halves_the_confidence() -> None:
    inputs = make_inputs([make_record("operational", days_old=365)], make_observed_state("no"))

    result = score(inputs)

    assert result.confidence == pytest.approx(STATE_CONFIDENCE * 0.5)


def test_undated_record_is_trusted_as_little_as_the_oldest_record() -> None:
    inputs = make_inputs([make_record("operational", days_old=None)], make_observed_state("no"))

    result = score(inputs)

    assert result.score == 100.0
    assert result.confidence == pytest.approx(STATE_CONFIDENCE * 0.3)
    assert result.observed_at == STATE_OBSERVED_AT


def test_result_traces_to_the_document_and_the_state_sources() -> None:
    inputs = make_inputs([make_record("operational", days_old=1)], make_observed_state("no"))

    result = score(inputs)

    assert result.source_ids == (DOCUMENT_ID, REPORT_SOURCE_ID)
    assert result.observed_at == datetime(2026, 10, 7, tzinfo=UTC)


def test_entity_without_a_matched_record_is_not_measured() -> None:
    inputs = make_inputs([], make_observed_state("no"))

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No official record found"
    assert result.score is None


@pytest.mark.parametrize("status_declared", [None, "under review"])
def test_missing_or_unknown_declared_status_is_not_measured(status_declared: str | None) -> None:
    inputs = make_inputs([make_record(status_declared)], make_observed_state("no"))

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No status in the register"


def test_unmeasured_operational_state_is_not_measured() -> None:
    not_measured_state = ScoreResult(
        status=ScoreStatus.NULL_NOT_MEASURED,
        null_reason="No recent report of whether this water point works",
    )
    inputs = make_inputs([make_record("operational")], not_measured_state)

    result = score(inputs)

    assert result.null_reason == "Current operating state not observed"


def test_missing_operational_state_result_is_not_measured() -> None:
    inputs = make_inputs([make_record("operational")], None)

    result = score(inputs)

    assert result.null_reason == "Current operating state not observed"


def test_state_taken_only_from_the_register_is_not_compared_with_itself() -> None:
    register_only_state = make_observed_state("yes", source_ids=(DOCUMENT_ID,))
    inputs = make_inputs([make_record("operational")], register_only_state)

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "Current operating state not observed"


def test_registry_runs_the_status_gap_adapter_after_operational_state() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    sub_ids_in_run_order = [adapter.spec.sub_id for adapter in adapters]
    assert sub_ids_in_run_order.index("1.2") < sub_ids_in_run_order.index("2.4")
