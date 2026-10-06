# What every adapter returns: one sub-variable result for one entity (Bible §6.1, ADR-003).
# The runner rejects anything that fails these checks before it writes
# scores.sub_variable_scores, so a bad adapter can never store a score without its evidence.

from enum import StrEnum
from typing import Annotated, Self
from uuid import UUID

from pydantic import AwareDatetime, BaseModel, ConfigDict, Field, model_validator

MIN_SCORE = 0
MAX_SCORE = 100
MIN_CONFIDENCE = 0
MAX_CONFIDENCE = 1

# Bible §6.1: a raw measurement in native units (litres/day, km, days) or a category
# such as "present" or "intermittent". NaN and infinity are never a measurement.
MeasuredValue = Annotated[float, Field(allow_inf_nan=False)] | str
Score = Annotated[float, Field(ge=MIN_SCORE, le=MAX_SCORE, allow_inf_nan=False)]
Confidence = Annotated[float, Field(ge=MIN_CONFIDENCE, le=MAX_CONFIDENCE, allow_inf_nan=False)]


class ScoreStatus(StrEnum):
    """Whether the adapter could measure the sub-variable. Matches the DB `status` column."""

    MEASURED = "measured"
    NULL_NOT_MEASURED = "null_not_measured"


class ScoreResult(BaseModel):
    """One adapter's answer for one entity and one sub-variable.

    Measured: value, score (0–100, direction-neutral), confidence (0–1), a timezone-aware
    observed_at and at least one source id are all required.
    Not measured: a non-blank null_reason is required, and value, score and confidence
    must stay empty — never 0, never a default.
    Implements Bible §6.1 (what every sub-variable emits) and the hard rule in CLAUDE.md §4.
    """

    # strict: True or "50" is a bug in the adapter, not a score, so never convert it.
    # frozen: the runner stores exactly what the adapter returned.
    model_config = ConfigDict(strict=True, frozen=True, extra="forbid")

    status: ScoreStatus
    value: MeasuredValue | None = None
    unit: str | None = None
    score: Score | None = None
    confidence: Confidence | None = None
    observed_at: AwareDatetime | None = None
    source_ids: tuple[UUID, ...] = ()
    null_reason: str | None = None

    @model_validator(mode="after")
    def check_fields_match_status(self) -> Self:
        if self.status is ScoreStatus.MEASURED:
            check_measured_result(self)
        else:
            check_not_measured_result(self)
        return self


def check_measured_result(result: ScoreResult) -> None:
    """Raise ValueError unless a measured result carries all its evidence (Bible §6.1)."""
    required_fields = {
        "value": result.value,
        "score": result.score,
        "confidence": result.confidence,
        "observed_at": result.observed_at,
    }
    missing_fields = [name for name, field_value in required_fields.items() if field_value is None]
    if missing_fields:
        raise ValueError(f"a measured result needs {', '.join(missing_fields)}")
    if not result.source_ids:
        raise ValueError("a measured result needs at least one source id")
    if result.null_reason is not None:
        raise ValueError("a measured result cannot have a null_reason")


def check_not_measured_result(result: ScoreResult) -> None:
    """Raise ValueError unless an unmeasured result has a reason and no number.

    Never 0 for missing data — Bible §6.1: under-mapped areas have absent attributes,
    not good ones.
    """
    if result.null_reason is None or not result.null_reason.strip():
        raise ValueError("a null_not_measured result needs a null_reason")
    measurement_fields = {
        "value": result.value,
        "score": result.score,
        "confidence": result.confidence,
    }
    filled_fields = [
        name for name, field_value in measurement_fields.items() if field_value is not None
    ]
    if filled_fields:
        raise ValueError(f"a null_not_measured result cannot have {', '.join(filled_fields)}")
