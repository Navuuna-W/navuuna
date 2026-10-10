# 2.4 Status gap for water points: the register declares one operating status and 1.2
# Operational state observes another. It is a V2 contributor and runs after 1.2. A water point
# declared operational that people report as not working is the gap this adapter scores.
# Spec: docs/modules/water.md "2.4 Status gap". Refs: D-18, FR-07.

from dataclasses import dataclass
from datetime import UTC, date, datetime, time
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

OPERATIONAL_STATE_SUB_ID = "1.2"

SPEC = AdapterSpec(
    module="water",
    sub_id="2.4",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.RECORDS],
    depends_on_sub_ids=[OPERATIONAL_STATE_SUB_ID],
    signal_description="Whether the declared operating status matches what is observed.",
)

DECLARED_OPERATIONAL = "operational"
DECLARED_NOT_OPERATIONAL = "not_operational"
# Score by the state 1.2 observed, for a water point the register declares operational.
SCORE_BY_OBSERVED_STATE_WHEN_DECLARED_OPERATIONAL = {"yes": 0.0, "intermittent": 50.0, "no": 100.0}
# Working when declared broken is not a discrepancy against the public (water.md, 2.4).
SCORE_WHEN_DECLARED_NOT_OPERATIONAL = 0.0

# Record age lowers V2 confidence (Bible §6.9, water.md rule 8): a record two years old or
# more keeps only the minimum factor.
STALE_AFTER_DAYS = 730
MIN_FRESHNESS_FACTOR = 0.3
MAX_FRESHNESS_FACTOR = 1.0

NO_OFFICIAL_RECORD = "No official record found"
NO_STATUS_IN_REGISTER = "No status in the register"
STATE_NOT_OBSERVED = "Current operating state not observed"


@dataclass(frozen=True)
class RegisterRecord:
    """One register record matched to the entity, with the status it declares."""

    document_id: UUID
    record_date: date | None
    status_declared: str | None
    alignment_confidence: float


def score(inputs: AdapterInputs) -> ScoreResult:
    """Compare the status the register declares with the state 1.2 observed.

    Input: the matched register records, the 1.2 result for the same entity and as_of.
    Output: the pair "declared → observed" with a 0–100 score, or null_not_measured with a
    reason from the spec. Implements docs/modules/water.md 2.4.
    """
    records = read_register_records(inputs.records or ())
    if not records:
        return not_measured(NO_OFFICIAL_RECORD)
    newest_record = records[0]
    declared = newest_record.status_declared
    if declared not in (DECLARED_OPERATIONAL, DECLARED_NOT_OPERATIONAL):
        return not_measured(NO_STATUS_IN_REGISTER)
    observed = inputs.sub_variable_results.get(OPERATIONAL_STATE_SUB_ID)
    document_ids = [record.document_id for record in records]
    if observed is None or not is_observed_independently(observed, document_ids):
        return not_measured(STATE_NOT_OBSERVED)
    if observed.confidence is None or observed.observed_at is None:
        return not_measured(STATE_NOT_OBSERVED)

    freshness_factor = calculate_freshness_factor(newest_record.record_date, inputs.as_of)
    weakest_confidence = min(newest_record.alignment_confidence, observed.confidence)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=f"{declared} → {observed.value}",
        score=calculate_gap_score(declared, str(observed.value)),
        confidence=weakest_confidence * freshness_factor,
        observed_at=find_newest_input_time(observed.observed_at, newest_record.record_date),
        source_ids=tuple(remove_duplicates(document_ids + list(observed.source_ids))),
    )


def is_observed_independently(observed: ScoreResult, document_ids: list[UUID]) -> bool:
    """True when 1.2 was measured from something other than the register itself.

    When every source of the 1.2 result is a register document, 1.2 only repeated the declared
    status, and comparing the record with itself would always show no gap (water.md, 1.2 note).
    """
    if observed.status is not ScoreStatus.MEASURED:
        return False
    return any(source_id not in document_ids for source_id in observed.source_ids)


def calculate_gap_score(declared: str, observed_state: str) -> float:
    """Return the 0–100 gap score for a declared status and an observed state."""
    if declared == DECLARED_NOT_OPERATIONAL:
        return SCORE_WHEN_DECLARED_NOT_OPERATIONAL
    return SCORE_BY_OBSERVED_STATE_WHEN_DECLARED_OPERATIONAL[observed_state]


def read_register_records(record_rows: tuple[InputRow, ...]) -> list[RegisterRecord]:
    """Read the matched record rows, most recent record date first (E7).

    Only the first record feeds the score; the others stay as sources. Rows without the two
    columns the database requires are skipped. Records with no date come last.
    """
    records: list[RegisterRecord] = []
    for record_row in record_rows:
        document_id = read_uuid(record_row.get("document_id"))
        alignment_confidence = record_row.get("alignment_confidence")
        if document_id is None or not isinstance(alignment_confidence, (int, float)):
            continue
        status_declared = record_row.get("status_declared")
        records.append(
            RegisterRecord(
                document_id=document_id,
                record_date=read_date(record_row.get("record_date")),
                status_declared=status_declared if isinstance(status_declared, str) else None,
                alignment_confidence=float(alignment_confidence),
            )
        )
    return sorted(records, key=get_record_sort_date, reverse=True)


def get_record_sort_date(record: RegisterRecord) -> date:
    """Return the date to sort a record by; an undated record sorts as the oldest."""
    return record.record_date or date.min


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


def find_newest_input_time(state_observed_at: datetime, record_date: date | None) -> datetime:
    """Return the time of the newest input used: the 1.2 observation or the record date."""
    if record_date is None:
        return state_observed_at
    # record_date is a plain date in the register, so it counts from the start of that day.
    record_time = datetime.combine(record_date, time.min, tzinfo=UTC)
    return max(state_observed_at, record_time)


def not_measured(reason: str) -> ScoreResult:
    """Build a null_not_measured result. Never 0 for missing data — Bible §6.1."""
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason=reason)


def remove_duplicates(source_ids: list[UUID]) -> list[UUID]:
    """Keep each source id once, in first-seen order (1.2 may already cite the document)."""
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
