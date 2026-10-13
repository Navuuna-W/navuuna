# Checks how boundary features become `area` entities: the external reference that keeps a
# ward the same ward across reloads, and the refusal to invent one when the code is missing.

import pytest

from base_layers.area_entities import BoundaryFeatureError, build_county_entity, build_ward_entity
from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature

SQUARE_GEOMETRY = {
    "type": "Polygon",
    "coordinates": [
        [[36.85, -1.27], [36.86, -1.27], [36.86, -1.28], [36.85, -1.28], [36.85, -1.27]]
    ],
}


def test_ward_becomes_an_area_with_an_iebc_ward_external_ref() -> None:
    ward_feature = LayerFeature(
        properties={"IEBC_WARDS": "Airbase", "PCODE": "KE0017", "FIRST_DIVI": "Pumwani"},
        geometry=SQUARE_GEOMETRY,
    )

    ward_entity = build_ward_entity(ward_feature)

    assert ward_entity.entity_type is EntityType.AREA
    assert ward_entity.external_ref == "iebc:ward:KE0017"
    assert ward_entity.name == "Airbase"
    assert ward_entity.metadata == {"area_kind": "ward", "division": "Pumwani"}


def test_ward_is_shared_so_it_has_no_module_and_no_radius() -> None:
    ward_feature = LayerFeature(
        properties={"IEBC_WARDS": "Airbase", "PCODE": "KE0017"}, geometry=SQUARE_GEOMETRY
    )

    ward_entity = build_ward_entity(ward_feature)

    assert ward_entity.module is None
    assert ward_entity.radius_m is None


def test_ward_without_a_division_leaves_the_division_out() -> None:
    ward_feature = LayerFeature(
        properties={"IEBC_WARDS": "Airbase", "PCODE": "KE0017", "FIRST_DIVI": None},
        geometry=SQUARE_GEOMETRY,
    )

    ward_entity = build_ward_entity(ward_feature)

    assert "division" not in ward_entity.metadata


def test_ward_without_a_code_is_refused_instead_of_given_a_made_up_reference() -> None:
    ward_feature = LayerFeature(
        properties={"IEBC_WARDS": "Airbase", "PCODE": " "}, geometry=SQUARE_GEOMETRY
    )

    with pytest.raises(BoundaryFeatureError, match="PCODE"):
        build_ward_entity(ward_feature)


def test_county_becomes_an_area_with_an_iebc_county_external_ref() -> None:
    county_feature = LayerFeature(
        properties={"adm1_name": "Nairobi", "adm1_pcode": "KE047"}, geometry=SQUARE_GEOMETRY
    )

    county_entity = build_county_entity(county_feature)

    assert county_entity.entity_type is EntityType.AREA
    assert county_entity.external_ref == "iebc:county:KE047"
    assert county_entity.name == "Nairobi"
    assert county_entity.metadata == {"area_kind": "county"}
