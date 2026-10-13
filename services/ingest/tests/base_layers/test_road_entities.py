# Checks how OpenStreetMap ways become `segment` entities of the roads module, and that a
# missing surface tag stays missing.

from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature
from base_layers.road_entities import build_road_entities

LINE_GEOMETRY = {"type": "LineString", "coordinates": [[36.85, -1.27], [36.86, -1.27]]}


def build_way(properties: dict[str, str | None]) -> LayerFeature:
    """An OSM way with id 456 and the given tags."""
    return LayerFeature(properties={"osm_id": "456", **properties}, geometry=LINE_GEOMETRY)


def test_road_becomes_a_segment_entity_of_the_roads_module() -> None:
    road = build_way({"highway": "primary", "surface": "asphalt", "name": "Ngong Road"})

    entity_rows = build_road_entities([road])

    assert len(entity_rows) == 1
    assert entity_rows[0].entity_type is EntityType.SEGMENT
    assert entity_rows[0].module == "roads"
    assert entity_rows[0].external_ref == "osm:w456"
    assert entity_rows[0].name == "Ngong Road"
    assert entity_rows[0].radius_m is None
    assert entity_rows[0].metadata == {"highway": "primary", "surface": "asphalt"}


def test_road_without_a_surface_tag_gets_no_surface_never_a_default() -> None:
    road = build_way({"highway": "residential", "surface": None})

    entity_rows = build_road_entities([road])

    assert entity_rows[0].metadata == {"highway": "residential"}


def test_footpath_is_kept_because_people_walk_it_to_a_water_point() -> None:
    footpath = build_way({"highway": "footway"})

    entity_rows = build_road_entities([footpath])

    assert len(entity_rows) == 1
    assert entity_rows[0].name == "Road (unnamed)"


def test_way_without_a_highway_tag_is_left_out() -> None:
    fence = build_way({"highway": None})

    entity_rows = build_road_entities([fence])

    assert entity_rows == []
