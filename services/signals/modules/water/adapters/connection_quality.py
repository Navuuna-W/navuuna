# 5.2 Connection quality for water points: the surface of the nearest mapped path people use
# to reach it. It is a V5 contributor and reads only the OSM ways the runner found within
# 200 m. A path with no surface tag is not measured, never assumed unpaved (tagging bias).
# Spec: docs/modules/water.md "5.2 Connection quality". Refs: D-18, FR-07, E10.

from dataclasses import dataclass
from datetime import datetime
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

ACCESS_SEARCH_RADIUS_M = 200

SPEC = AdapterSpec(
    module="water",
    sub_id="5.2",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.NEARBY_WAYS],
    nearby_radius_m=ACCESS_SEARCH_RADIUS_M,
    signal_description="Surface of the nearest mapped path used to reach the water point.",
)

SCORE_SEALED = 100.0
SCORE_FIRM = 75.0
SCORE_GRAVEL = 60.0
SCORE_UNPAVED = 30.0
# OSM `surface` tag → score (docs/modules/water.md, 5.2).
SCORE_BY_SURFACE = {
    "paved": SCORE_SEALED,
    "asphalt": SCORE_SEALED,
    "concrete": SCORE_SEALED,
    "paving_stones": SCORE_FIRM,
    "compacted": SCORE_FIRM,
    "gravel": SCORE_GRAVEL,
    "fine_gravel": SCORE_GRAVEL,
    "unpaved": SCORE_UNPAVED,
    "dirt": SCORE_UNPAVED,
    "ground": SCORE_UNPAVED,
    "earth": SCORE_UNPAVED,
    "mud": SCORE_UNPAVED,
}
# OSM is community-mapped, so a tag is trusted only this far.
CONFIDENCE_OSM_TAG = 0.6

NO_MAPPED_PATH = "No mapped path within 200 m"
SURFACE_NOT_MAPPED = "Path surface not mapped in OSM"
SURFACE_NOT_RECOGNISED = "Path surface value not recognised"
MAP_SOURCE_NOT_RECORDED = "Map source of this path not recorded"


@dataclass(frozen=True)
class NearbyWay:
    """One OSM way near the water point: how far it is, its tags and its map source."""

    distance_m: float
    surface: str | None
    highway: str | None
    source_id: UUID | None
    observed_at: datetime | None


def score(inputs: AdapterInputs) -> ScoreResult:
    """Score the surface of the nearest mapped path to the water point.

    Input: the OSM ways within ACCESS_SEARCH_RADIUS_M, each with distance_m. Output: the
    nearest way's surface (with its highway class) and a 0–100 score, or null_not_measured
    with a reason from the spec — a missing tag is never a default (E10, Bible §6.8).
    Implements docs/modules/water.md 5.2.
    """
    ways = read_nearby_ways(inputs.nearby_ways or ())
    if not ways:
        return not_measured(NO_MAPPED_PATH)
    nearest_way = min(ways, key=get_way_distance)
    if nearest_way.surface is None:
        return not_measured(SURFACE_NOT_MAPPED)
    if nearest_way.surface not in SCORE_BY_SURFACE:
        return not_measured(SURFACE_NOT_RECOGNISED)
    if nearest_way.source_id is None or nearest_way.observed_at is None:
        return not_measured(MAP_SOURCE_NOT_RECORDED)
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value=describe_way(nearest_way.surface, nearest_way.highway),
        score=SCORE_BY_SURFACE[nearest_way.surface],
        confidence=CONFIDENCE_OSM_TAG,
        observed_at=nearest_way.observed_at,
        source_ids=(nearest_way.source_id,),
    )


def describe_way(surface: str, highway: str | None) -> str:
    """Return the value shown for a way: its surface, with its highway class when mapped."""
    if highway is None:
        return surface
    return f"{surface} ({highway})"


def read_nearby_ways(way_rows: tuple[InputRow, ...]) -> list[NearbyWay]:
    """Read the nearby way rows; a row without a usable distance cannot be ranked and is skipped."""
    ways: list[NearbyWay] = []
    for way_row in way_rows:
        way = read_nearby_way(way_row)
        if way is not None:
            ways.append(way)
    return ways


def read_nearby_way(way_row: InputRow) -> NearbyWay | None:
    """Read one way row. Tags and the map source live in metadata (D-05); any may be missing."""
    distance_m = way_row.get("distance_m")
    if isinstance(distance_m, bool) or not isinstance(distance_m, (int, float)):
        return None
    metadata = way_row.get("metadata")
    if not isinstance(metadata, dict):
        metadata = {}
    return NearbyWay(
        distance_m=float(distance_m),
        surface=read_tag(metadata.get("surface")),
        highway=read_tag(metadata.get("highway")),
        source_id=read_uuid(metadata.get("source_id")),
        observed_at=read_aware_timestamp(metadata.get("observed_at")),
    )


def get_way_distance(way: NearbyWay) -> float:
    """Return the distance to compare ways by."""
    return way.distance_m


def read_tag(raw_value: object) -> str | None:
    """Read an OSM tag value; None if missing or blank."""
    if not isinstance(raw_value, str) or not raw_value.strip():
        return None
    return raw_value.strip()


def not_measured(reason: str) -> ScoreResult:
    """Build a null_not_measured result. Never 0 for missing data — Bible §6.1."""
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason=reason)


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
