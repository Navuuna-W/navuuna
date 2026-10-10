# 2.5 Record staleness for water points: how many days the register record lags behind the
# newest time anyone observed the water point. It is a V2 contributor and reads the matched
# record, the entity's observations and the date of the map extract that holds the entity.
# Spec: docs/modules/water.md "2.5 Record staleness". Refs: D-18, FR-07.

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
    sub_id="2.5",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.ENTITY, InputKind.OBSERVATIONS, InputKind.RECORDS],
    signal_description="How many days the register record lags behind our newest observation.",
)

MAX_SCORE = 100.0
# A record two years or more behind our observations is fully stale (Bible §6.9).
STALE_AFTER_DAYS = 730
# Dates are reliable when present (docs/modules/water.md, 2.5).
CONFIDENCE_DATES_PRESENT = 0.9
UNIT_DAYS = "days"

NO_OFFICIAL_RECORD = "No official record found"
NO_INSPECTION_DATE = "No inspection date in the register"
NO_OBSERVATION = "No observation to compare the record with"


@dataclass(frozen=True)
class Sighting:
    """One time a source observed the water point: a report, or the map extract holding it."""

    source_id: UUID
    observed_at: datetime


@dataclass(frozen=True)
class RegisterRecord:
    """One register record matched to the entity: the document it came from and its date."""

    document_id: UUID
    record_date: date | None


def score(inputs: AdapterInputs) -> ScoreResult:
    """Count the days between the newest record and the newest observation of the entity.

    Input: the entity row, its observations, the matched register records and as_of.
    Output: the lag in days (0 when the record is newer) with a 0–100 score, or
    null_not_measured with a reason from the spec. Implements docs/modules/water.md 2.5.
    """
    records = read_register_records(inputs.records or ())
    if not records:
        return not_measured(NO_OFFICIAL_RECORD)
    newest_record = records[0]
    if newest_record.record_date is None:
        return not_measured(NO_INSPECTION_DATE)
    sightings = read_sightings(inputs.entity, inputs.observations or (), inputs.as_of)
    if not sightings:
        return not_measured(NO_OBSERVATION)

    newest_sighting = max(sightings, key=get_sighting_time)
    lag_days = (newest_sighting.observed_at.date() - newest_record.record_date).days
    stale_days = max(0, lag_days)
    record_time = datetime.combine(newest_record.record_date, time.min, tzinfo=UTC)
    source_ids = [record.document_id for record in records] + [newest_sighting.source_id]
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=float(stale_days),
        unit=UNIT_DAYS,
        score=min(stale_days / STALE_AFTER_DAYS, 1.0) * MAX_SCORE,
        confidence=CONFIDENCE_DATES_PRESENT,
        observed_at=max(newest_sighting.observed_at, record_time),
        source_ids=tuple(remove_duplicates(source_ids)),
    )


def read_sightings(
    entity: InputRow | None, observations: tuple[InputRow, ...], as_of: datetime
) -> list[Sighting]:
    """Return every sighting of the entity up to as_of: its observations and its map extract.

    The map extract counts as a sighting ("any source" in the spec): the extract date is when
    the map last showed the water point. D-05 stores it in the entity's metadata.
    """
    sighting_rows = list(observations)
    metadata = entity.get("metadata") if entity is not None else None
    if isinstance(metadata, dict):
        sighting_rows.append(metadata)
    sightings: list[Sighting] = []
    for sighting_row in sighting_rows:
        sighting = read_sighting(sighting_row)
        if sighting is not None and sighting.observed_at <= as_of:
            sightings.append(sighting)
    return sightings


def read_sighting(sighting_row: InputRow) -> Sighting | None:
    """Read a row's source_id and observed_at as a Sighting; None if either is unusable."""
    source_id = read_uuid(sighting_row.get("source_id"))
    observed_at = read_aware_timestamp(sighting_row.get("observed_at"))
    if source_id is None or observed_at is None:
        return None
    return Sighting(source_id=source_id, observed_at=observed_at)


def get_sighting_time(sighting: Sighting) -> datetime:
    """Return the time to compare sightings by."""
    return sighting.observed_at


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
