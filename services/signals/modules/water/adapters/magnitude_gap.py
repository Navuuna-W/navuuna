# 2.2 Magnitude gap for water points: how far the reported production falls short of the
# capacity the register declares. It is a V2 contributor and reads only the matched register
# record, so both figures and the date come from the same document.
# Spec: docs/modules/water.md "2.2 Magnitude gap". Refs: D-18, FR-07.

from dataclasses import dataclass
from datetime import UTC, date, datetime, time
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

SPEC = AdapterSpec(
    module="water",
    sub_id="2.2",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.RECORDS],
    signal_description="How far reported production falls short of the declared capacity.",
)

MAX_SCORE = 100.0
# The gap is a share of the declared capacity; the score only uses the part between 0 and 1.
MIN_SCORED_GAP = 0.0
MAX_SCORED_GAP = 1.0

# Record age lowers V2 confidence (Bible §6.9, water.md rule 8): a record two years old or
# more keeps only the minimum factor.
STALE_AFTER_DAYS = 730
MIN_FRESHNESS_FACTOR = 0.3
MAX_FRESHNESS_FACTOR = 1.0

NO_OFFICIAL_RECORD = "No official record found"
NO_INSPECTION_DATE = "No inspection date in the register"
NO_RATED_YIELD = "No rated yield in the register"
NO_PRODUCTION_FIGURE = "No production figure in the register"
RATED_YIELD_NOT_POSITIVE = "Rated yield in the register is not a positive number"


@dataclass(frozen=True)
class RegisterRecord:
    """One register record matched to the entity, with the figures this adapter compares."""

    document_id: UUID
    record_date: date | None
    rated_yield_m3d: float | None
    reported_production_m3d: float | None
    alignment_confidence: float


def score(inputs: AdapterInputs) -> ScoreResult:
    """Compare the declared capacity with the reported production of the newest record.

    Input: the matched register records and as_of. Output: the gap as a share of the declared
    capacity (negative when it produces more than rated) with a 0–100 score, or
    null_not_measured with a reason from the spec — never 0 for a missing figure.
    Implements docs/modules/water.md 2.2 and Bible §6.1.
    """
    records = read_register_records(inputs.records or ())
    if not records:
        return not_measured(NO_OFFICIAL_RECORD)
    newest_record = records[0]
    # The record is the only input, so its date is the result's observed_at: no date, no result.
    if newest_record.record_date is None:
        return not_measured(NO_INSPECTION_DATE)
    if newest_record.rated_yield_m3d is None:
        return not_measured(NO_RATED_YIELD)
    if newest_record.reported_production_m3d is None:
        return not_measured(NO_PRODUCTION_FIGURE)
    if newest_record.rated_yield_m3d <= 0:
        return not_measured(RATED_YIELD_NOT_POSITIVE)

    shortfall_m3d = newest_record.rated_yield_m3d - newest_record.reported_production_m3d
    gap = shortfall_m3d / newest_record.rated_yield_m3d
    scored_gap = min(MAX_SCORED_GAP, max(MIN_SCORED_GAP, gap))
    freshness_factor = calculate_freshness_factor(newest_record.record_date, inputs.as_of)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=gap,
        score=scored_gap * MAX_SCORE,
        confidence=newest_record.alignment_confidence * freshness_factor,
        observed_at=datetime.combine(newest_record.record_date, time.min, tzinfo=UTC),
        source_ids=tuple(remove_duplicates([record.document_id for record in records])),
    )


def read_register_records(record_rows: tuple[InputRow, ...]) -> list[RegisterRecord]:
    """Read the matched record rows, most recent record date first (E7).

    Only the first record feeds the score; the others stay as sources. Records with no date
    come last, so a dated record is always preferred.
    """
    records: list[RegisterRecord] = []
    for record_row in record_rows:
        record = read_register_record(record_row)
        if record is not None:
            records.append(record)
    return sorted(records, key=get_record_sort_date, reverse=True)


def read_register_record(record_row: InputRow) -> RegisterRecord | None:
    """Read one record row, or None when it lacks the two columns the database requires."""
    document_id = read_uuid(record_row.get("document_id"))
    alignment_confidence = read_number(record_row.get("alignment_confidence"))
    if document_id is None or alignment_confidence is None:
        return None
    return RegisterRecord(
        document_id=document_id,
        record_date=read_date(record_row.get("record_date")),
        rated_yield_m3d=read_number(record_row.get("rated_yield_m3d")),
        reported_production_m3d=read_number(record_row.get("reported_production_m3d")),
        alignment_confidence=alignment_confidence,
    )


def get_record_sort_date(record: RegisterRecord) -> date:
    """Return the date to sort a record by; an undated record sorts as the oldest."""
    if record.record_date is None:
        return date.min
    return record.record_date


def calculate_freshness_factor(record_date: date, as_of: datetime) -> float:
    """Return how much a record's age lowers confidence: 1.0 for new, 0.3 for two years old.

    Implements water.md rule 8.
    """
    record_age_days = (as_of.date() - record_date).days
    freshness_factor = 1 - record_age_days / STALE_AFTER_DAYS
    return min(MAX_FRESHNESS_FACTOR, max(MIN_FRESHNESS_FACTOR, freshness_factor))


def not_measured(reason: str) -> ScoreResult:
    """Build a null_not_measured result. Never 0 for missing data — Bible §6.1."""
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason=reason)


def remove_duplicates(source_ids: list[UUID]) -> list[UUID]:
    """Keep each source id once, in first-seen order (two records may share a document)."""
    unique_ids: list[UUID] = []
    for source_id in source_ids:
        if source_id not in unique_ids:
            unique_ids.append(source_id)
    return unique_ids


def read_number(raw_value: object) -> float | None:
    """Read a number from a JSON row; None if missing or not a number (True is not a number)."""
    if isinstance(raw_value, bool) or not isinstance(raw_value, (int, float)):
        return None
    return float(raw_value)


def read_date(raw_value: object) -> date | None:
    """Parse a "YYYY-MM-DD" date from a JSON row; None if missing or malformed."""
    if not isinstance(raw_value, str):
        return None
    try:
        return date.fromisoformat(raw_value)
    except ValueError:
        return None


def read_uuid(raw_value: object) -> UUID | None:
    """Parse a UUID from a JSON row; None if missing or malformed."""
    if not isinstance(raw_value, str):
        return None
    try:
        return UUID(raw_value)
    except ValueError:
        return None
