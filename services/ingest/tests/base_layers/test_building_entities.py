# Checks how OpenStreetMap building footprints become `parcel` entities of the disabled
# land module (buildings rule, docs/modules/land.md).

from base_layers.building_entities import build_building_entities
from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature

FOOTPRINT_GEOMETRY = {
    "type": "MultiPolygon",
    "coordinates": [
        [[[36.85, -1.27], [36.86, -1.27], [36.86, -1.28], [36.85, -1.28], [36.85, -1.27]]]
    ],
}


def build_footprint(properties: dict[str, str | None]) -> LayerFeature:
    """A closed OSM way with id 789 and the given tags."""
    return LayerFeature(
        properties={"osm_id": None, "osm_way_id": "789", **properties},
        geometry=FOOTPRINT_GEOMETRY,
    )


def test_building_becomes_a_parcel_entity_of_the_land_module() -> None:
    building = build_footprint({"building": "school", "name": "Olympic Primary"})

    entity_rows = build_building_entities([building])

    assert len(entity_rows) == 1
    assert entity_rows[0].entity_type is EntityType.PARCEL
    assert entity_rows[0].module == "land"
    assert entity_rows[0].external_ref == "osm:w789"
    assert entity_rows[0].name == "Olympic Primary"
    assert entity_rows[0].metadata == {"building": "school"}


def test_unnamed_building_says_it_is_unnamed() -> None:
    building = build_footprint({"building": "yes"})

    entity_rows = build_building_entities([building])

    assert entity_rows[0].name == "Building (unnamed)"


def test_footprint_without_a_building_tag_is_left_out() -> None:
    water_works_outline = build_footprint({"man_made": "water_works"})

    entity_rows = build_building_entities([water_works_outline])

    assert entity_rows == []
