# Turns OpenStreetMap building footprints into `parcel` entities of the land module (work
# pack D-05, buildings rule in docs/modules/land.md). The land module is disabled in v1, so
# these entities are stored and counted but never scored or drawn by default.

from base_layers.entity_row import EntityRow
from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature
from base_layers.osm_feature import build_osm_external_ref, read_osm_tags

# Never rename after the first load: the loader finds the layer's earlier entities by it.
OSM_BUILDINGS_LAYER = "osm_buildings"

LAND_MODULE = "land"

BUILDING_TAG_NAME = "building"

# `name` is required by the table; the brackets say this is not a real name.
UNNAMED_BUILDING_NAME = "Building (unnamed)"


def build_building_entities(features: list[LayerFeature]) -> list[EntityRow]:
    """Turn the footprints of an OSM buildings layer file into parcel entities.

    Input: the features of the buildings layer. Output: one entity per feature that has a
    `building` tag; anything else is left out.
    Implements FR-01 and DEC-07 (buildings load as `parcel` entities with module `land`).
    """
    entity_rows: list[EntityRow] = []
    for feature in features:
        tags = read_osm_tags(feature, (BUILDING_TAG_NAME, "name"))
        if BUILDING_TAG_NAME not in tags:
            continue
        entity_rows.append(build_building_entity(feature, tags))
    return entity_rows


def build_building_entity(feature: LayerFeature, tags: dict[str, str]) -> EntityRow:
    """Build the parcel entity for one building footprint from the tags it has."""
    return EntityRow(
        entity_type=EntityType.PARCEL,
        module=LAND_MODULE,
        name=tags.get("name", UNNAMED_BUILDING_NAME),
        external_ref=build_osm_external_ref(feature),
        geometry=feature.geometry,
        metadata={BUILDING_TAG_NAME: tags[BUILDING_TAG_NAME]},
    )
