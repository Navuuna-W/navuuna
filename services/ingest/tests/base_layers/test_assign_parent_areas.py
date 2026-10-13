# Checks, against a migrated database and acting as nv_ingest, that entities are linked to
# the area they sit in and that an entity outside every area gets no parent.

from datetime import date
from typing import Any

import pytest

from base_layers.assign_parent_areas import assign_parent_areas
from base_layers.entity_row import EntityRow
from base_layers.entity_type import EntityType
from base_layers.load_base_layer import BaseLayer, load_base_layer
from database.connection import DatabaseConnection
from sources.register_sources import register_sources
from sources.source_definition import SourceDefinition
from sources.source_kind import SourceKind

SELECT_PARENT_REFS_SQL = """
    SELECT entity.external_ref, parent_area.external_ref AS parent_external_ref
    FROM core.entities AS entity
    LEFT JOIN core.entities AS parent_area ON parent_area.id = entity.parent_area_id
    WHERE entity.metadata ->> 'base_layer' = %s
"""

TEST_SOURCE = SourceDefinition(
    name="A test parent-area source that is not in the catalogue",
    kind=SourceKind.VECTOR,
    licence="Test licence",
    attribution="Test attribution",
)
WARDS_LAYER_NAME = "test_parent_wards"
WATER_POINTS_LAYER_NAME = "test_parent_water_points"

# The test ward is the square from longitude 36.85 to 36.86 and latitude -1.28 to -1.27.
TEST_WARD = EntityRow(
    entity_type=EntityType.AREA,
    name="Test ward",
    external_ref="test:parent:ward",
    geometry={
        "type": "Polygon",
        "coordinates": [
            [[36.85, -1.27], [36.86, -1.27], [36.86, -1.28], [36.85, -1.28], [36.85, -1.27]]
        ],
    },
)


def build_water_point(reference: str, longitude: float) -> EntityRow:
    """A water point at latitude -1.275 and the given longitude."""
    return EntityRow(
        entity_type=EntityType.POINT,
        module="water",
        name="A water point",
        external_ref=f"test:parent:{reference}",
        geometry={"type": "Point", "coordinates": [longitude, -1.275]},
        radius_m=25.0,
    )


def select_parent_refs(connection: DatabaseConnection) -> dict[str, Any]:
    """Water point external reference → its parent area's external reference, or None."""
    rows = connection.execute(SELECT_PARENT_REFS_SQL, [WATER_POINTS_LAYER_NAME]).fetchall()
    return {row["external_ref"]: row["parent_external_ref"] for row in rows}


@pytest.fixture
def connection_with_layers(ingest_connection: DatabaseConnection) -> DatabaseConnection:
    """The nv_ingest connection with one ward, one water point inside it and one outside."""
    register_sources(ingest_connection, [TEST_SOURCE])
    layers = {
        WARDS_LAYER_NAME: [TEST_WARD],
        WATER_POINTS_LAYER_NAME: [
            build_water_point("inside", longitude=36.855),
            build_water_point("outside", longitude=36.9),
        ],
    }
    for layer_name, entity_rows in layers.items():
        load_base_layer(
            ingest_connection,
            BaseLayer(
                layer_name=layer_name,
                source_name=TEST_SOURCE.name,
                extract_date=date(2026, 10, 12),
                entity_rows=entity_rows,
            ),
        )
    return ingest_connection


def test_water_point_inside_a_ward_gets_that_ward_as_its_parent(
    connection_with_layers: DatabaseConnection,
) -> None:
    changed_count = assign_parent_areas(
        connection_with_layers, WATER_POINTS_LAYER_NAME, WARDS_LAYER_NAME
    )

    assert changed_count == 1
    assert select_parent_refs(connection_with_layers)["test:parent:inside"] == "test:parent:ward"


def test_water_point_outside_every_ward_keeps_no_parent(
    connection_with_layers: DatabaseConnection,
) -> None:
    assign_parent_areas(connection_with_layers, WATER_POINTS_LAYER_NAME, WARDS_LAYER_NAME)

    assert select_parent_refs(connection_with_layers)["test:parent:outside"] is None


def test_assigning_parents_a_second_time_changes_nothing(
    connection_with_layers: DatabaseConnection,
) -> None:
    assign_parent_areas(connection_with_layers, WATER_POINTS_LAYER_NAME, WARDS_LAYER_NAME)

    changed_count = assign_parent_areas(
        connection_with_layers, WATER_POINTS_LAYER_NAME, WARDS_LAYER_NAME
    )

    assert changed_count == 0
