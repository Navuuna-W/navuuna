# 4.4 Competition for water points: how many other water points within 500 m draw on the same
# users and supply. It is a V4 contributor and reads the entities the runner found nearby and
# in the same ward. A ward with too few mapped water points is not measured, so an
# under-mapped place never looks uncontested (tagging bias).
# Spec: docs/modules/water.md "4.4 Competition". Refs: D-18, FR-07.

from dataclasses import dataclass
from datetime import datetime
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

COMPETITION_RADIUS_M = 500

SPEC = AdapterSpec(
    module="water",
    sub_id="4.4",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.NEARBY_ENTITIES, InputKind.SAME_AREA_ENTITIES],
    nearby_radius_m=COMPETITION_RADIUS_M,
    signal_description="How many other water points are within 500 m of this one.",
)

WATER_MODULE = "water"
MAX_SCORE = 100.0
# Ten or more other water points nearby is the most competition the score shows.
COMPETITION_SATURATION_COUNT = 10
# Below this many mapped water points, the ward's map is too thin to count neighbours from.
MIN_MAPPED_WATER_POINTS_IN_WARD = 5
# The same-area rows leave out the entity being scored, so it is added back to the ward count.
SCORED_ENTITY_COUNT = 1
# OSM completeness varies by ward (docs/modules/water.md, 4.4).
CONFIDENCE_OSM_COUNT = 0.5
UNIT_WATER_POINTS = "water points"

TOO_FEW_MAPPED = "Too few water points mapped in this ward to judge"
MAP_SOURCE_NOT_RECORDED = "Map source of nearby water points not recorded"


@dataclass(frozen=True)
class MapSource:
    """The map extract one counted entity came from, and when that extract was taken."""

    source_id: UUID
    observed_at: datetime


def score(inputs: AdapterInputs) -> ScoreResult:
    """Count the other water points within COMPETITION_RADIUS_M of the water point.

    Input: the entities within the radius and the water entities of the same ward (both
    without retired entities and without the entity itself). Output: the count and a 0–100
    score, or null_not_measured with a reason from the spec. Implements docs/modules/water.md
    4.4 and Bible §6.8 (tagging bias).
    """
    ward_water_points = inputs.same_area_entities or ()
    mapped_in_ward_count = len(ward_water_points) + SCORED_ENTITY_COUNT
    if mapped_in_ward_count < MIN_MAPPED_WATER_POINTS_IN_WARD:
        return not_measured(TOO_FEW_MAPPED)
    nearby_water_points = select_water_points(inputs.nearby_entities or ())
    map_sources = read_map_sources(list(nearby_water_points) + list(ward_water_points))
    if not map_sources:
        return not_measured(MAP_SOURCE_NOT_RECORDED)

    nearby_count = len(nearby_water_points)
    saturation_share = min(nearby_count / COMPETITION_SATURATION_COUNT, 1.0)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=float(nearby_count),
        unit=UNIT_WATER_POINTS,
        score=saturation_share * MAX_SCORE,
        confidence=CONFIDENCE_OSM_COUNT,
        observed_at=max(map_source.observed_at for map_source in map_sources),
        source_ids=tuple(remove_duplicates([map_source.source_id for map_source in map_sources])),
    )


def select_water_points(nearby_rows: tuple[InputRow, ...]) -> list[InputRow]:
    """Keep the nearby entities of the water module; roads and buildings do not compete."""
    return [nearby_row for nearby_row in nearby_rows if nearby_row.get("module") == WATER_MODULE]


def read_map_sources(entity_rows: list[InputRow]) -> list[MapSource]:
    """Return the map source of every counted entity that has one recorded (D-05 metadata)."""
    map_sources: list[MapSource] = []
    for entity_row in entity_rows:
        metadata = entity_row.get("metadata")
        if not isinstance(metadata, dict):
            continue
        source_id = read_uuid(metadata.get("source_id"))
        observed_at = read_aware_timestamp(metadata.get("observed_at"))
        if source_id is not None and observed_at is not None:
            map_sources.append(MapSource(source_id=source_id, observed_at=observed_at))
    return map_sources


def not_measured(reason: str) -> ScoreResult:
    """Build a null_not_measured result. Never 0 for missing data — Bible §6.1."""
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason=reason)


def remove_duplicates(source_ids: list[UUID]) -> list[UUID]:
    """Keep each source id once, in first-seen order (most entities share one extract)."""
    unique_ids: list[UUID] = []
    for source_id in source_ids:
        if source_id not in unique_ids:
            unique_ids.append(source_id)
    return unique_ids


def read_aware_timestamp(raw_value: object) -> datetime | None:
    """Parse an ISO 8601 timestamp from a JSON row; None if missing, malformed or zoneless."""
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
