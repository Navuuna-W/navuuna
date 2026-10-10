# 2.1 Existence gap for water points: the register says a scheme exists here, and 1.1 Presence
# says it does not. It is V2's gate and runs after 1.1: no_gap → passed, gap → failed (V2
# cannot_assess and the existence-gap finding is raised), not measured → V2 provisional.
# Spec: docs/modules/water.md "2.1 Existence gap". Refs: D-18, FR-07.

from dataclasses import dataclass
from datetime import UTC, date, datetime, time
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

PRESENCE_SUB_ID = "1.1"

SPEC = AdapterSpec(
    module="water",
    sub_id="2.1",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.RECORDS],
    depends_on_sub_ids=[PRESENCE_SUB_ID],
    signal_description="Whether the register lists a water point that is not on the ground.",
)

GAP = "gap"
NO_GAP = "no_gap"
SCORE_GAP = 100.0
SCORE_NO_GAP = 0.0

# The value 1.1 Presence gives a water point that is not there (adapters/presence.py).
PRESENCE_ABSENT = "absent"

# Record age lowers V2 confidence (Bible §6.9, water.md rule 8): a record two years old or
# more keeps only the minimum factor.
STALE_AFTER_DAYS = 730
MIN_FRESHNESS_FACTOR = 0.3
MAX_FRESHNESS_FACTOR = 1.0

NO_OFFICIAL_RECORD = "No official record found"
PRESENCE_NOT_CHECKED = "Presence could not be checked"


@dataclass(frozen=True)
class RegisterRecord:
    """One register record matched to the entity: the document it came from and its date."""

    document_id: UUID
    record_date: date | None


def score(inputs: AdapterInputs) -> ScoreResult:
    """Decide whether the register and the ground disagree about this water point existing.

    Input: the matched register records, the 1.1 result for the same entity and as_of.
    Output: value "gap" or "no_gap" with confidence, or null_not_measured with a reason from
    the spec — never a guess. Implements docs/modules/water.md 2.1 and Bible §6.3.
    """
    records = read_register_records(inputs.records or ())
    if not records:
        return not_measured(NO_OFFICIAL_RECORD)
    presence = inputs.sub_variable_results.get(PRESENCE_SUB_ID)
    if presence is None or presence.status is not ScoreStatus.MEASURED:
        return not_measured(PRESENCE_NOT_CHECKED)
    if presence.confidence is None or presence.observed_at is None:
        return not_measured(PRESENCE_NOT_CHECKED)

    newest_record = records[0]
    freshness_factor = calculate_freshness_factor(newest_record.record_date, inputs.as_of)
    is_gap = presence.value == PRESENCE_ABSENT
    document_ids = [record.document_id for record in records]
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=GAP if is_gap else NO_GAP,
        score=SCORE_GAP if is_gap else SCORE_NO_GAP,
        confidence=presence.confidence * freshness_factor,
        observed_at=find_newest_input_time(presence.observed_at, newest_record.record_date),
        source_ids=tuple(remove_duplicates(document_ids + list(presence.source_ids))),
    )


def read_register_records(record_rows: tuple[InputRow, ...]) -> list[RegisterRecord]:
    """Read the matched record rows, most recent record date first (E7).

    Only the first record feeds the score; the others stay as sources. Records with no date
    come last, so a dated record is always preferred.
    """
    records: list[RegisterRecord] = []
    for record_row in record_rows:
        document_id = read_uuid(record_row.get("document_id"))
        if document_id is not None:
            record_date = read_date(record_row.get("record_date"))
            records.append(RegisterRecord(document_id=document_id, record_date=record_date))
    return sorted(records, key=get_record_sort_date, reverse=True)


def get_record_sort_date(record: RegisterRecord) -> date:
    """Return the date to sort a record by; an undated record sorts as the oldest."""
    if record.record_date is None:
        return date.min
    return record.record_date


def calculate_freshness_factor(record_date: date | None, as_of: datetime) -> float:
    """Return how much a record's age lowers confidence: 1.0 for new, 0.3 for two years old.

    An undated record gets the minimum factor: we cannot show it is recent, so it is trusted
    as little as the oldest record, never as a fresh one. Implements water.md rule 8.
    """
    if record_date is None:
        return MIN_FRESHNESS_FACTOR
    record_age_days = (as_of.date() - record_date).days
    freshness_factor = 1 - record_age_days / STALE_AFTER_DAYS
    return min(MAX_FRESHNESS_FACTOR, max(MIN_FRESHNESS_FACTOR, freshness_factor))


def find_newest_input_time(presence_observed_at: datetime, record_date: date | None) -> datetime:
    """Return the time of the newest input used: the 1.1 observation or the record date."""
    if record_date is None:
        return presence_observed_at
    # record_date is a plain date in the register, so it counts from the start of that day.
    record_time = datetime.combine(record_date, time.min, tzinfo=UTC)
    return max(presence_observed_at, record_time)


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
