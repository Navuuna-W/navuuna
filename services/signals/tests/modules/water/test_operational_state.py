# Tests for the water 1.2 Operational state adapter: every score rule and null reason in
# docs/modules/water.md "1.2 Operational state", plus the registry finding the file.

from datetime import UTC, datetime, timedelta
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreStatus
from modules.water.adapters.operational_state import score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
REPORT_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000002")
OLDER_DOCUMENT_ID = UUID("00000000-0000-7000-8000-000000000003")
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_report(operating_state: str, days_ago: int) -> InputRow:
    return {
        "source_id": str(REPORT_SOURCE_ID),
        "observed_at": (AS_OF - timedelta(days=days_ago)).isoformat(),
        "payload": {"existence": "exists", "operating_state": operating_state},
    }


def make_record(
    status_declared: str | None, days_old: int | None = 0, document_id: UUID = DOCUMENT_ID
) -> InputRow:
    record_date = None
    if days_old is not None:
        record_date = (AS_OF - timedelta(days=days_old)).date().isoformat()
    return {
        "document_id": str(document_id),
        "record_date": record_date,
        "status_declared": status_declared,
    }


def make_inputs(
    observations: list[InputRow] | None = None, records: list[InputRow] | None = None
) -> AdapterInputs:
    return AdapterInputs(
        as_of=AS_OF, observations=tuple(observations or []), records=tuple(records or [])
    )


@pytest.mark.parametrize(
    ("operating_state", "expected_value", "expected_score"),
    [("working", "yes", 100.0), ("intermittent", "intermittent", 50.0), ("not_working", "no", 0.0)],
)
def test_recent_community_report_sets_the_state(
    operating_state: str, expected_value: str, expected_score: float
) -> None:
    inputs = make_inputs([make_report(operating_state, days_ago=5)])

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert (result.value, result.score, result.confidence) == (expected_value, expected_score, 0.8)
    assert result.source_ids == (REPORT_SOURCE_ID,)
    assert result.observed_at == AS_OF - timedelta(days=5)


def test_report_older_than_ninety_days_has_lower_confidence() -> None:
    inputs = make_inputs([make_report("working", days_ago=200)])

    result = score(inputs)

    assert (result.value, result.confidence) == ("yes", 0.6)


def test_newest_report_wins_over_older_reports() -> None:
    inputs = make_inputs([make_report("working", days_ago=40), make_report("not_working", 3)])

    result = score(inputs)

    assert result.value == "no"


def test_not_sure_report_is_ignored() -> None:
    inputs = make_inputs([make_report("not_sure", days_ago=1), make_report("working", 30)])

    result = score(inputs)

    assert result.value == "yes"
    assert result.observed_at == AS_OF - timedelta(days=30)


def test_report_wins_over_the_register_and_a_contradiction_lowers_confidence() -> None:
    inputs = make_inputs([make_report("not_working", days_ago=5)], [make_record("operational")])

    result = score(inputs)

    assert result.value == "no"
    assert result.confidence == pytest.approx(0.8 * 0.6)
    assert result.source_ids == (REPORT_SOURCE_ID, DOCUMENT_ID)


def test_register_agreeing_with_the_report_changes_nothing() -> None:
    inputs = make_inputs([make_report("working", days_ago=5)], [make_record("operational")])

    result = score(inputs)

    assert result.confidence == 0.8
    assert result.source_ids == (REPORT_SOURCE_ID,)


def test_intermittent_report_does_not_contradict_the_register() -> None:
    inputs = make_inputs([make_report("intermittent", days_ago=5)], [make_record("operational")])

    result = score(inputs)

    assert result.confidence == 0.8


def test_register_status_alone_gives_half_confidence() -> None:
    inputs = make_inputs(records=[make_record("not_operational", days_old=0)])

    result = score(inputs)

    assert (result.value, result.score) == ("no", 0.0)
    assert result.confidence == pytest.approx(0.5)
    assert result.source_ids == (DOCUMENT_ID,)
    assert result.observed_at == datetime(2026, 10, 8, tzinfo=UTC)


def test_register_status_alone_loses_confidence_with_record_age() -> None:
    inputs = make_inputs(records=[make_record("operational", days_old=365)])

    result = score(inputs)

    assert result.value == "yes"
    assert result.confidence == pytest.approx(0.5 * 0.5)


def test_only_the_most_recent_record_declares_the_status() -> None:
    older_record = make_record("operational", days_old=900, document_id=OLDER_DOCUMENT_ID)
    inputs = make_inputs(records=[older_record, make_record(None, days_old=10)])

    result = score(inputs)

    assert result.null_reason == "No recent report of whether this water point works"


def test_no_report_and_no_record_is_not_measured_not_broken() -> None:
    inputs = make_inputs()

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No recent report of whether this water point works"
    assert result.score is None


def test_report_older_than_the_lookback_is_not_used() -> None:
    inputs = make_inputs([make_report("working", days_ago=400)])

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED


def test_undated_register_status_alone_is_not_measured() -> None:
    inputs = make_inputs(records=[make_record("operational", days_old=None)])

    result = score(inputs)

    assert result.null_reason == "No recent report of whether this water point works"


def test_registry_finds_the_operational_state_adapter_in_the_water_module() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    code_ref_by_sub_id = {adapter.spec.sub_id: adapter.code_ref for adapter in adapters}
    assert code_ref_by_sub_id["1.2"] == "modules/water/adapters/operational_state.py"
