# Turns OpenStreetMap water and sanitation features into `point` entities of the water module
# (work pack D-05). Runs between layer_file.py (read) and load_base_layer.py (write).

from base_layers.entity_row import EntityRow
from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature
from base_layers.osm_feature import build_osm_external_ref, read_osm_tags

# Never rename after the first load: the loader finds the layer's earlier entities by it.
OSM_WATER_POINTS_LAYER = "osm_water_points"

WATER_MODULE = "water"

# The Bible gives a point entity a radius but no number for it. 25 m covers one water point
# and its yard without reaching the next one in a dense settlement.
DEFAULT_WATER_POINT_RADIUS_M = 25.0

# OSM tag → value → the kind of water point it marks. The list is the one in work pack D-05.
# `man_made` is checked first: many Nairobi features carry both tags, such as
# amenity=water_point with man_made=water_well, and `man_made` says what is really there.
WATER_POINT_KINDS_BY_TAG: dict[str, dict[str, str]] = {
    "man_made": {
        "water_well": "Water well",
        "water_tap": "Water tap",
        "water_tower": "Water tower",
        "water_works": "Water works",
        "reservoir_covered": "Covered reservoir",
        "wastewater_plant": "Wastewater plant",
    },
    "amenity": {
        "drinking_water": "Drinking water point",
        "water_point": "Water point",
        "toilets": "Toilets",
    },
}

# A storage tank counts only when OSM says it holds water.
STORAGE_TANK_VALUE = "storage_tank"
STORAGE_TANK_WATER_CONTENT = "water"
STORAGE_TANK_KIND = "Water storage tank"

# The tags kept in metadata, when the feature has them.
WATER_POINT_TAG_NAMES = ("amenity", "man_made", "content", "operator", "access")


def build_water_point_entities(features: list[LayerFeature]) -> list[EntityRow]:
    """Turn the water features of an OSM layer file into point entities.

    Input: the features of the water points layer. Output: one entity per feature that is a
    water point; any other feature (say, a storage tank for oil) is left out.
    Implements FR-01 (OSM water loaded as entities).
    """
    entity_rows: list[EntityRow] = []
    for feature in features:
        water_point_kind = find_water_point_kind(feature)
        if water_point_kind is None:
            continue
        entity_rows.append(build_water_point_entity(feature, water_point_kind))
    return entity_rows


def find_water_point_kind(feature: LayerFeature) -> str | None:
    """Return what kind of water point the feature is, or None when it is not one."""
    tags = read_osm_tags(feature, WATER_POINT_TAG_NAMES)
    for tag_name, kinds_by_value in WATER_POINT_KINDS_BY_TAG.items():
        tag_value = tags.get(tag_name)
        if tag_value in kinds_by_value:
            return kinds_by_value[tag_value]
    is_storage_tank = tags.get("man_made") == STORAGE_TANK_VALUE
    if is_storage_tank and tags.get("content") == STORAGE_TANK_WATER_CONTENT:
        return STORAGE_TANK_KIND
    return None


def build_water_point_entity(feature: LayerFeature, water_point_kind: str) -> EntityRow:
    """Build the point entity for one water feature of a known kind.

    The geometry is passed on as OSM has it. A water works drawn as an outline is turned
    into a point by the loader, which is where the geometry maths lives.
    """
    tags = read_osm_tags(feature, (*WATER_POINT_TAG_NAMES, "name"))
    # `name` is required by the table. An unnamed water point is named by its kind, and
    # the brackets say so, so nobody reads it as a real name.
    name = tags.pop("name", f"{water_point_kind} (unnamed)")
    return EntityRow(
        entity_type=EntityType.POINT,
        module=WATER_MODULE,
        name=name,
        external_ref=build_osm_external_ref(feature),
        geometry=feature.geometry,
        radius_m=DEFAULT_WATER_POINT_RADIUS_M,
        metadata={**tags, "water_point_kind": water_point_kind},
    )
