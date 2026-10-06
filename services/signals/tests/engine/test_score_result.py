# Tests for ScoreResult, the contract every adapter returns (Bible §6.1, ADR-003).
# Each rejection test is one way an adapter could store a score without its evidence,
# or a 0 where the truth is "not measured".

from datetime import UTC, datetime
from typing import Any
from uuid import UUID

import pytest
from pydantic import ValidationError

from engine.score_result import ScoreResult, ScoreStatus

SOURCE_ID = UUID("0192a5b0-0000-7000-8000-000000000001")
OBSERVED_AT = datetime(2026, 9, 30, 12, 0, tzinfo=UTC)


def measured_fields(**overrides: Any) -> dict[str, Any]:
    fields: dict[str, Any] = {
        "status": ScoreStatus.MEASURED,
        "value": 12.5,
        "unit": "m3/day",
        "score": 75,
        "confidence": 0.7,
        "observed_at": OBSERVED_AT,
        "source_ids": (SOURCE_ID,),
    }
    fields.update(overrides)
    return fields


def not_measured_fields(**overrides: Any) -> dict[str, Any]:
    fields: dict[str, Any] = {
        "status": ScoreStatus.NULL_NOT_MEASURED,
        "null_reason": "No official record found",
    }
    fields.update(overrides)
    return fields


def test_a_complete_measured_result_is_accepted() -> None:
    result = ScoreResult(**measured_fields())

    assert result.score == 75
    assert result.source_ids == (SOURCE_ID,)


def test_a_measured_result_can_carry_a_category_value() -> None:
    result = ScoreResult(**measured_fields(value="present", unit=None, score=100))

    assert result.value == "present"


def test_a_not_measured_result_with_a_reason_is_accepted() -> None:
    result = ScoreResult(**not_measured_fields())

    assert result.score is None
    assert result.value is None


@pytest.mark.parametrize("missing_field", ["value", "score", "confidence", "observed_at"])
def test_measured_result_without_a_required_field_is_rejected(missing_field: str) -> None:
    fields = measured_fields()
    del fields[missing_field]

    with pytest.raises(ValidationError, match=missing_field):
        ScoreResult(**fields)


def test_measured_result_without_sources_is_rejected() -> None:
    with pytest.raises(ValidationError, match="at least one source id"):
        ScoreResult(**measured_fields(source_ids=()))


def test_measured_result_with_a_null_reason_is_rejected() -> None:
    with pytest.raises(ValidationError, match="cannot have a null_reason"):
        ScoreResult(**measured_fields(null_reason="No official record found"))


@pytest.mark.parametrize("reason", [None, "", "   "])
def test_not_measured_result_without_a_reason_is_rejected(reason: str | None) -> None:
    with pytest.raises(ValidationError, match="needs a null_reason"):
        ScoreResult(**not_measured_fields(null_reason=reason))


@pytest.mark.parametrize("filled_field", ["value", "score", "confidence"])
def test_not_measured_result_is_never_given_a_zero(filled_field: str) -> None:
    with pytest.raises(ValidationError, match=f"cannot have {filled_field}"):
        ScoreResult(**not_measured_fields(**{filled_field: 0}))


@pytest.mark.parametrize("score", [-1, 100.5, float("nan")])
def test_score_outside_0_to_100_is_rejected(score: float) -> None:
    with pytest.raises(ValidationError):
        ScoreResult(**measured_fields(score=score))


@pytest.mark.parametrize("confidence", [-0.1, 1.1, float("inf")])
def test_confidence_outside_0_to_1_is_rejected(confidence: float) -> None:
    with pytest.raises(ValidationError):
        ScoreResult(**measured_fields(confidence=confidence))


@pytest.mark.parametrize("bad_score", [True, "75"])
def test_score_is_never_converted_from_a_bool_or_string(bad_score: object) -> None:
    with pytest.raises(ValidationError):
        ScoreResult(**measured_fields(score=bad_score))


def test_observed_at_without_a_timezone_is_rejected() -> None:
    naive_time = datetime(2026, 9, 30, 12, 0)

    with pytest.raises(ValidationError):
        ScoreResult(**measured_fields(observed_at=naive_time))


def test_unknown_fields_are_rejected() -> None:
    with pytest.raises(ValidationError):
        ScoreResult(**measured_fields(weight=0.4))


def test_a_result_cannot_be_changed_after_the_adapter_returns_it() -> None:
    result = ScoreResult(**measured_fields())

    with pytest.raises(ValidationError):
        result.score = 10  # type: ignore[misc]
