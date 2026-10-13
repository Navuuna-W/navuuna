# Turns OpenStreetMap ways with a `highway` tag into `segment` entities of the roads module
# (work pack D-05). Runs between layer_file.py (read) and load_base_layer.py (write).

from base_layers.entity_row import EntityRow
from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature
from base_layers.osm_feature import build_osm_external_ref, read_osm_tags

# Never rename after the first load: the loader finds the layer's earlier entities by it.
OSM_ROADS_LAYER = "osm_roads"

ROADS_MODULE = "roads"

HIGHWAY_TAG_NAME = "highway"
# The tags kept in metadata, when the way has them. Water adapter 5.2 reads `highway` and
# `surface` from the way nearest to a water point (docs/modules/water.md).
ROAD_TAG_NAMES = (HIGHWAY_TAG_NAME, "surface")

# `name` is required by the table; the brackets say this is not a real name.
UNNAMED_ROAD_NAME = "Road (unnamed)"


def build_road_entities(features: list[LayerFeature]) -> list[EntityRow]:
    """Turn the ways of an OSM roads layer file into segment entities.

    Input: the features of the roads layer. Output: one entity per way that has a `highway`
    tag. Every class is kept, footpaths included: in an informal settlement the path people
    walk to a water point is often a footway, and adapter 5.2 scores the nearest way.
    Implements FR-01 (OSM roads loaded as entities).
    """
    entity_rows: list[EntityRow] = []
    for feature in features:
        tags = read_osm_tags(feature, ROAD_TAG_NAMES)
        if HIGHWAY_TAG_NAME not in tags:
            continue
        entity_rows.append(build_road_entity(feature, tags))
    return entity_rows


def build_road_entity(feature: LayerFeature, tags: dict[str, str]) -> EntityRow:
    """Build the segment entity for one way.

    Inputs: the way and the road tags it has. A way with no `surface` tag gets no surface
    in metadata: the adapter then says "not mapped" instead of guessing (Bible §6.1).
    """
    name_tags = read_osm_tags(feature, ("name",))
    return EntityRow(
        entity_type=EntityType.SEGMENT,
        module=ROADS_MODULE,
        name=name_tags.get("name", UNNAMED_ROAD_NAME),
        external_ref=build_osm_external_ref(feature),
        geometry=feature.geometry,
        metadata=tags,
    )
