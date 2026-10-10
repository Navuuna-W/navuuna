# 1.1 Presence for water points: is there a water point where the entity says? It is V1's
# gate, so the rollup reads its value: present → passed, absent → failed (V1 cannot_assess),
# not measured → V1 provisional. Spec: docs/modules/water.md "1.1 Presence". Refs: D-18, FR-07.

from dataclasses import dataclass
from datetime import datetime, timedelta
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

SPEC = AdapterSpec(
    module="water",
    sub_id="1.1",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.ENTITY, InputKind.OBSERVATIONS],
    signal_description="Whether a water point is on the ground where the entity says it is.",
)

PRESENT = "present"
ABSENT = "absent"
SCORE_PRESENT = 100.0
SCORE_ABSENT = 0.0

# Confidence levels from docs/modules/water.md, 1.1 Presence.
CONFIDENCE_IN_OSM_EXTRACT = 0.7
CONFIDENCE_REPORTED_ABSENT = 0.7
CONFIDENCE_RECENT_EXISTS_REPORT = 0.9
# A "does not exist here" report that does not make the entity absent still lowers our trust
# in "present" (DEC-14, E11).
CONTRADICTION_CONFIDENCE_FACTOR = 0.6

# Two different people within 90 days are needed to call a water point absent (DEC-14).
MIN_ABSENCE_REPORTERS = 2
RECENT_OBSERVATION_DAYS = 90

OSM_REF_PREFIX = "osm:"
EXISTS = "exists"
DOES_NOT_EXIST_HERE = "does_not_exist_here"

NO_GROUND_OBSERVATION = "No ground observation of this water point yet"
MAP_SOURCE_NOT_RECORDED = "Map source of this water point not recorded"


@dataclass(frozen=True)
class GroundReport:
    """One observation that says whether the water point exists (payload v1 `existence`)."""

    existence: str
    observed_at: datetime
    contributor_id: str | None
    source_id: UUID


@dataclass(frozen=True)
class MapSource:
    """Where the OSM extract that holds this entity came from, and when it was taken."""

    source_id: UUID
    observed_at: datetime


def score(inputs: AdapterInputs) -> ScoreResult:
    """Decide whether the water point is present, absent or not measured.

    Input: the entity row, its observations and as_of. Output: value "present" or "absent"
    with confidence, or null_not_measured with a reason from the spec — never a guess.
    Implements docs/modules/water.md 1.1 and Bible §6.3 (the gate's three states).
    """
    map_source = find_map_source(inputs.entity)
    reports = select_recent_ground_reports(inputs.observations or (), inputs.as_of)
    absence_reports = [report for report in reports if report.existence == DOES_NOT_EXIST_HERE]
    exists_reports = [report for report in reports if report.existence == EXISTS]

    if is_reported_absent(absence_reports, exists_reports):
        return build_result(ABSENT, CONFIDENCE_REPORTED_ABSENT, absence_reports, map_source)
    if exists_reports:
        confidence = lower_if_contradicted(CONFIDENCE_RECENT_EXISTS_REPORT, absence_reports)
        return build_result(PRESENT, confidence, exists_reports + absence_reports, map_source)
    if inputs.entity is None or not is_in_current_osm_extract(inputs.entity):
        return not_measured(NO_GROUND_OBSERVATION)
    if map_source is None:
        return not_measured(MAP_SOURCE_NOT_RECORDED)
    confidence = lower_if_contradicted(CONFIDENCE_IN_OSM_EXTRACT, absence_reports)
    return build_result(PRESENT, confidence, absence_reports, map_source)


def select_recent_ground_reports(
    observations: tuple[InputRow, ...], as_of: datetime
) -> list[GroundReport]:
    """Return the ground reports seen in the last RECENT_OBSERVATION_DAYS up to as_of."""
    window_start = as_of - timedelta(days=RECENT_OBSERVATION_DAYS)
    reports: list[GroundReport] = []
    for observation in observations:
        report = read_ground_report(observation)
        if report is not None and window_start <= report.observed_at <= as_of:
            reports.append(report)
    return reports


def read_ground_report(observation: InputRow) -> GroundReport | None:
    """Read one observation row as a GroundReport, or None if it says nothing about existence."""
    payload = observation.get("payload")
    if not isinstance(payload, dict):
        return None
    existence = payload.get("existence")
    if existence not in (EXISTS, DOES_NOT_EXIST_HERE):
        return None
    observed_at = read_aware_timestamp(observation.get("observed_at"))
    source_id = read_uuid(observation.get("source_id"))
    if observed_at is None or source_id is None:
        return None
    contributor_id = observation.get("contributor_id")
    return GroundReport(
        existence=str(existence),
        observed_at=observed_at,
        contributor_id=str(contributor_id) if contributor_id is not None else None,
        source_id=source_id,
    )


def is_reported_absent(
    absence_reports: list[GroundReport], exists_reports: list[GroundReport]
) -> bool:
    """True when enough different people said "not here" and nobody said "exists" since.

    Anonymous reports (no contributor_id) are not counted: they cannot prove two people.
    """
    reporters = {report.contributor_id for report in absence_reports if report.contributor_id}
    if len(reporters) < MIN_ABSENCE_REPORTERS:
        return False
    newest_absence = max(report.observed_at for report in absence_reports)
    return not any(report.observed_at > newest_absence for report in exists_reports)


def is_in_current_osm_extract(entity: InputRow) -> bool:
    """True when the entity came from OSM and the latest extract still holds it (not retired)."""
    external_ref = entity.get("external_ref")
    is_from_osm = isinstance(external_ref, str) and external_ref.startswith(OSM_REF_PREFIX)
    return is_from_osm and entity.get("retired_at") is None


def find_map_source(entity: InputRow | None) -> MapSource | None:
    """Return the OSM extract's source and date for an entity in the extract, else None.

    D-05 stores both in metadata because core.entities has no source column. Missing or
    malformed values give None: the adapter then says so, it never guesses a source.
    """
    if entity is None or not is_in_current_osm_extract(entity):
        return None
    metadata = entity.get("metadata")
    if not isinstance(metadata, dict):
        return None
    source_id = read_uuid(metadata.get("source_id"))
    observed_at = read_aware_timestamp(metadata.get("observed_at"))
    if source_id is None or observed_at is None:
        return None
    return MapSource(source_id=source_id, observed_at=observed_at)


def lower_if_contradicted(confidence: float, absence_reports: list[GroundReport]) -> float:
    """Lower confidence in "present" when someone recently said it is not there (DEC-14)."""
    if absence_reports:
        return confidence * CONTRADICTION_CONFIDENCE_FACTOR
    return confidence


def build_result(
    value: str,
    confidence: float,
    reports: list[GroundReport],
    map_source: MapSource | None,
) -> ScoreResult:
    """Build a measured result whose source_ids and observed_at cover every input used."""
    source_ids = [report.source_id for report in reports]
    observed_times = [report.observed_at for report in reports]
    if map_source is not None:
        source_ids.insert(0, map_source.source_id)
        observed_times.append(map_source.observed_at)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=value,
        score=SCORE_PRESENT if value == PRESENT else SCORE_ABSENT,
        confidence=confidence,
        observed_at=max(observed_times),
        source_ids=tuple(remove_duplicates(source_ids)),
    )


def not_measured(reason: str) -> ScoreResult:
    """Build a null_not_measured result. Never 0 for missing data — Bible §6.1."""
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason=reason)


def remove_duplicates(source_ids: list[UUID]) -> list[UUID]:
    """Keep each source id once, in first-seen order (one person may report twice)."""
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


def read_uuid(raw_value: object) -> UUID | None:
    """Parse a UUID from a JSON row; None if missing or malformed."""
    if not isinstance(raw_value, str):
        return None
    try:
        return UUID(raw_value)
    except ValueError:
        return None
