# 1.2 Operational state for water points: is it working now? It is a V1 contributor and feeds
# 2.4 Status gap. The newest community report decides; with no report, the status the register
# declares is used at lower confidence.
# Spec: docs/modules/water.md "1.2 Operational state". Refs: D-18, FR-07, DEC-14.

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

SPEC = AdapterSpec(
    module="water",
    sub_id="1.2",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.OBSERVATIONS, InputKind.RECORDS],
    signal_description="Whether a water point is working now.",
)

YES = "yes"
INTERMITTENT = "intermittent"
NO = "no"
SCORE_BY_STATE = {YES: 100.0, INTERMITTENT: 50.0, NO: 0.0}

# Payload v1 `operating_state` → state. "not_sure" is left out on purpose: it says nothing.
STATE_BY_REPORTED_STATE = {"working": YES, "intermittent": INTERMITTENT, "not_working": NO}
# Register `status_declared` → state.
STATE_BY_DECLARED_STATUS = {"operational": YES, "not_operational": NO}

# Confidence levels from docs/modules/water.md, 1.2 Operational state.
CONFIDENCE_RECENT_REPORT = 0.8
CONFIDENCE_OLDER_REPORT = 0.6
CONFIDENCE_REGISTER_ONLY = 0.5
# A report and the register saying opposite things lowers our trust in the report (E11).
CONTRADICTION_CONFIDENCE_FACTOR = 0.6

RECENT_OBSERVATION_DAYS = 90
OBSERVATION_LOOKBACK_DAYS = 365

# Record age lowers the confidence of a register-only answer (water.md rule 8).
STALE_AFTER_DAYS = 730
MIN_FRESHNESS_FACTOR = 0.3
MAX_FRESHNESS_FACTOR = 1.0

NO_RECENT_REPORT = "No recent report of whether this water point works"


@dataclass(frozen=True)
class StateReport:
    """One community report of whether the water point works (payload v1 `operating_state`)."""

    state: str
    observed_at: datetime
    source_id: UUID


@dataclass(frozen=True)
class DeclaredStatus:
    """The operating status the newest register record declares, and where it came from."""

    state: str
    record_date: date | None
    document_id: UUID


def score(inputs: AdapterInputs) -> ScoreResult:
    """Decide whether the water point works: yes, intermittent, no, or not measured.

    Input: the entity's observations, its matched register records and as_of. Output: the
    state with confidence, or null_not_measured — never a guess. Precedence: newest community
    report, then the register. Implements docs/modules/water.md 1.2 and DEC-14.
    """
    reports = select_reports_in_lookback(inputs.observations or (), inputs.as_of)
    declared_status = find_declared_status(inputs.records or ())
    if reports:
        newest_report = max(reports, key=get_report_time)
        return build_result_from_report(newest_report, declared_status, inputs.as_of)
    if declared_status is None or declared_status.record_date is None:
        # An undated status cannot give the result an observed_at, so it is not used alone.
        return not_measured(NO_RECENT_REPORT)
    return build_result_from_register(declared_status, declared_status.record_date, inputs.as_of)


def build_result_from_report(
    report: StateReport, declared_status: DeclaredStatus | None, as_of: datetime
) -> ScoreResult:
    """Build the result for a community report, lowering confidence if the register disagrees."""
    recent_since = as_of - timedelta(days=RECENT_OBSERVATION_DAYS)
    is_recent = report.observed_at >= recent_since
    confidence = CONFIDENCE_RECENT_REPORT if is_recent else CONFIDENCE_OLDER_REPORT
    source_ids = [report.source_id]
    if declared_status is not None and is_contradiction(report.state, declared_status.state):
        # E11: keep both sources, keep the report's value, trust it less.
        confidence = confidence * CONTRADICTION_CONFIDENCE_FACTOR
        source_ids.append(declared_status.document_id)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=report.state,
        score=SCORE_BY_STATE[report.state],
        confidence=confidence,
        observed_at=report.observed_at,
        source_ids=tuple(source_ids),
    )


def build_result_from_register(
    declared_status: DeclaredStatus, record_date: date, as_of: datetime
) -> ScoreResult:
    """Build the result when the register's declared status is the only input."""
    freshness_factor = calculate_freshness_factor(record_date, as_of)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=declared_status.state,
        score=SCORE_BY_STATE[declared_status.state],
        confidence=CONFIDENCE_REGISTER_ONLY * freshness_factor,
        observed_at=datetime.combine(record_date, time.min, tzinfo=UTC),
        source_ids=(declared_status.document_id,),
    )


def is_contradiction(reported_state: str, declared_state: str) -> bool:
    """True when one source says working and the other says not working.

    "intermittent" contradicts neither: it is partly both.
    """
    return {reported_state, declared_state} == {YES, NO}


def select_reports_in_lookback(
    observations: tuple[InputRow, ...], as_of: datetime
) -> list[StateReport]:
    """Return the state reports seen in the last OBSERVATION_LOOKBACK_DAYS up to as_of."""
    window_start = as_of - timedelta(days=OBSERVATION_LOOKBACK_DAYS)
    reports: list[StateReport] = []
    for observation in observations:
        report = read_state_report(observation)
        if report is not None and window_start <= report.observed_at <= as_of:
            reports.append(report)
    return reports


def read_state_report(observation: InputRow) -> StateReport | None:
    """Read one observation row as a StateReport, or None if it says nothing about working."""
    payload = observation.get("payload")
    if not isinstance(payload, dict):
        return None
    reported_state = payload.get("operating_state")
    if not isinstance(reported_state, str) or reported_state not in STATE_BY_REPORTED_STATE:
        return None
    observed_at = read_aware_timestamp(observation.get("observed_at"))
    source_id = read_uuid(observation.get("source_id"))
    if observed_at is None or source_id is None:
        return None
    return StateReport(
        state=STATE_BY_REPORTED_STATE[reported_state], observed_at=observed_at, source_id=source_id
    )


def get_report_time(report: StateReport) -> datetime:
    """Return the time to compare reports by."""
    return report.observed_at


def find_declared_status(record_rows: tuple[InputRow, ...]) -> DeclaredStatus | None:
    """Return the status the most recent record declares (E7), or None if it declares none.

    Only the most recent record counts: an older record's status is not the latest word.
    """
    if not record_rows:
        return None
    newest_row = max(record_rows, key=get_record_sort_date)
    document_id = read_uuid(newest_row.get("document_id"))
    declared = newest_row.get("status_declared")
    if document_id is None or not isinstance(declared, str):
        return None
    if declared not in STATE_BY_DECLARED_STATUS:
        return None
    return DeclaredStatus(
        state=STATE_BY_DECLARED_STATUS[declared],
        record_date=read_date(newest_row.get("record_date")),
        document_id=document_id,
    )


def get_record_sort_date(record_row: InputRow) -> date:
    """Return the date to sort a record row by; an undated record sorts as the oldest."""
    return read_date(record_row.get("record_date")) or date.min


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


def read_aware_timestamp(raw_value: object) -> datetime | None:
    """Parse an ISO 8601 timestamp from a JSON row; None if missing, malformed or zoneless.

    A timestamp without a zone cannot be compared with as_of, so it is treated as missing.
    """
    if not isinstance(raw_value, str):
        return None
    try:
        timestamp = datetime.fromisoformat(raw_value)
    except ValueError:
        return None
    if timestamp.tzinfo is None:
        return None
    return timestamp


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
