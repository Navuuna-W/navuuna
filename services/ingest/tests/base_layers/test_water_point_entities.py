# Checks how OpenStreetMap water and sanitation features become `point` entities of the
# water module: which tags count, what an unnamed one is called, and what is left out.

from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature
from base_layers.water_point_entities import (
    DEFAULT_WATER_POINT_RADIUS_M,
    build_water_point_entities,
)

POINT_GEOMETRY = {"type": "Point", "coordinates": [36.85, -1.27]}


def build_node(properties: dict[str, str | None]) -> LayerFeature:
    """An OSM node with id 123 and the given tags."""
    return LayerFeature(properties={"osm_id": "123", **properties}, geometry=POINT_GEOMETRY)


def test_water_tap_becomes_a_water_point_entity_with_a_radius() -> None:
    water_tap = build_node({"man_made": "water_tap", "name": "Kibera tap 4"})

    entity_rows = build_water_point_entities([water_tap])

    assert len(entity_rows) == 1
    assert entity_rows[0].entity_type is EntityType.POINT
    assert entity_rows[0].module == "water"
    assert entity_rows[0].external_ref == "osm:n123"
    assert entity_rows[0].name == "Kibera tap 4"
    assert entity_rows[0].radius_m == DEFAULT_WATER_POINT_RADIUS_M


def test_unnamed_water_point_is_named_by_its_kind_and_says_it_is_unnamed() -> None:
    unnamed_toilets = build_node({"amenity": "toilets", "name": None})

    entity_rows = build_water_point_entities([unnamed_toilets])

    assert entity_rows[0].name == "Toilets (unnamed)"


def test_metadata_keeps_only_the_tags_the_feature_has() -> None:
    water_well = build_node({"man_made": "water_well", "operator": None, "access": "yes"})

    entity_rows = build_water_point_entities([water_well])

    assert entity_rows[0].metadata == {
        "man_made": "water_well",
        "access": "yes",
        "water_point_kind": "Water well",
    }


def test_storage_tank_counts_only_when_it_holds_water() -> None:
    water_tank = build_node({"man_made": "storage_tank", "content": "water"})
    oil_tank = build_node({"man_made": "storage_tank", "content": "oil"})
    tank_with_unknown_content = build_node({"man_made": "storage_tank"})

    entity_rows = build_water_point_entities([water_tank, oil_tank, tank_with_unknown_content])

    assert len(entity_rows) == 1
    assert entity_rows[0].metadata["water_point_kind"] == "Water storage tank"


def test_feature_that_is_not_a_water_point_is_left_out() -> None:
    bench = build_node({"amenity": "bench"})

    entity_rows = build_water_point_entities([bench])

    assert entity_rows == []
