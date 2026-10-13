# Checks the stable external reference of an OpenStreetMap feature (node, way or relation)
# and that only the tags a feature really has are read.

import pytest

from base_layers.layer_file import LayerFeature
from base_layers.osm_feature import OsmFeatureError, build_osm_external_ref, read_osm_tags

POINT_GEOMETRY = {"type": "Point", "coordinates": [36.85, -1.27]}
LINE_GEOMETRY = {"type": "LineString", "coordinates": [[36.85, -1.27], [36.86, -1.27]]}
POLYGON_GEOMETRY = {
    "type": "MultiPolygon",
    "coordinates": [
        [[[36.85, -1.27], [36.86, -1.27], [36.86, -1.28], [36.85, -1.28], [36.85, -1.27]]]
    ],
}


def test_node_gets_an_osm_n_external_ref() -> None:
    node_feature = LayerFeature(properties={"osm_id": "123"}, geometry=POINT_GEOMETRY)

    external_ref = build_osm_external_ref(node_feature)

    assert external_ref == "osm:n123"


def test_open_way_gets_an_osm_w_external_ref() -> None:
    road_feature = LayerFeature(properties={"osm_id": "456"}, geometry=LINE_GEOMETRY)

    external_ref = build_osm_external_ref(road_feature)

    assert external_ref == "osm:w456"


def test_closed_way_gets_an_osm_w_external_ref_from_its_way_id() -> None:
    outline_feature = LayerFeature(
        properties={"osm_id": None, "osm_way_id": "789"}, geometry=POLYGON_GEOMETRY
    )

    external_ref = build_osm_external_ref(outline_feature)

    assert external_ref == "osm:w789"


def test_relation_gets_an_osm_r_external_ref() -> None:
    relation_feature = LayerFeature(
        properties={"osm_id": "55", "osm_way_id": None}, geometry=POLYGON_GEOMETRY
    )

    external_ref = build_osm_external_ref(relation_feature)

    assert external_ref == "osm:r55"


def test_feature_without_an_osm_id_is_refused_instead_of_given_a_made_up_reference() -> None:
    feature_without_id = LayerFeature(properties={"amenity": "toilets"}, geometry=POINT_GEOMETRY)

    with pytest.raises(OsmFeatureError):
        build_osm_external_ref(feature_without_id)


def test_missing_and_blank_tags_are_left_out_never_defaulted() -> None:
    feature = LayerFeature(
        properties={"highway": "residential", "surface": None, "name": "  "},
        geometry=LINE_GEOMETRY,
    )

    tags = read_osm_tags(feature, ("highway", "surface", "name", "lanes"))

    assert tags == {"highway": "residential"}
