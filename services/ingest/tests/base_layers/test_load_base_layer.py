# Checks the base-layer loader against a migrated database, acting as nv_ingest: reloads keep
# entity IDs, and missing entities are retired and never deleted (work pack D-05).

from datetime import date
from typing import Any

import pytest

from base_layers.entity_row import EntityRow
from base_layers.entity_type import EntityType
from base_layers.load_base_layer import BaseLayer, BaseLayerLoadError, load_base_layer
from database.connection import DatabaseConnection
from sources.register_sources import register_sources
from sources.source_definition import SourceDefinition
from sources.source_kind import SourceKind

SELECT_LAYER_ENTITIES_SQL = """
    SELECT id, name, external_ref, metadata, retired_at, public.ST_SRID(geom) AS srid,
           public.ST_GeometryType(geom) AS geometry_type,
           public.ST_X(public.ST_PointOnSurface(geom)) AS longitude
    FROM core.entities
    WHERE metadata ->> 'base_layer' = %s
    ORDER BY external_ref
"""
SELECT_SOURCE_ID_SQL = "SELECT id FROM core.sources WHERE name = %s"

TEST_SOURCE = SourceDefinition(
    name="A test boundary source that is not in the catalogue",
    kind=SourceKind.VECTOR,
    licence="Test licence",
    attribution="Test attribution",
)
TEST_LAYER_NAME = "test_wards"
OTHER_LAYER_NAME = "test_other_wards"


def build_ward(ward_code: str, name: str = "A ward") -> EntityRow:
    """An area entity with a small square geometry."""
    return EntityRow(
        entity_type=EntityType.AREA,
        name=name,
        external_ref=f"test:ward:{ward_code}",
        geometry={
            "type": "Polygon",
            "coordinates": [
                [[36.85, -1.27], [36.86, -1.27], [36.86, -1.28], [36.85, -1.28], [36.85, -1.27]]
            ],
        },
        metadata={"area_kind": "ward"},
    )


def build_layer(entity_rows: list[EntityRow], layer_name: str = TEST_LAYER_NAME) -> BaseLayer:
    """A layer from the test source, extracted on 12 Oct 2026."""
    return BaseLayer(
        layer_name=layer_name,
        source_name=TEST_SOURCE.name,
        extract_date=date(2026, 10, 12),
        entity_rows=entity_rows,
    )


def select_layer_entities(
    connection: DatabaseConnection, layer_name: str = TEST_LAYER_NAME
) -> list[dict[str, Any]]:
    """Every stored entity of a layer, ordered by external reference."""
    return connection.execute(SELECT_LAYER_ENTITIES_SQL, [layer_name]).fetchall()


@pytest.fixture
def connection_with_source(ingest_connection: DatabaseConnection) -> DatabaseConnection:
    """The nv_ingest connection, with the test source already in core.sources."""
    register_sources(ingest_connection, [TEST_SOURCE])
    return ingest_connection


def test_loading_a_layer_stores_its_entities_in_srid_4326(
    connection_with_source: DatabaseConnection,
) -> None:
    layer = build_layer([build_ward("A"), build_ward("B")])

    result = load_base_layer(connection_with_source, layer)

    stored_entities = select_layer_entities(connection_with_source)
    assert result.loaded_count == 2
    assert result.retired_count == 0
    assert [entity["external_ref"] for entity in stored_entities] == ["test:ward:A", "test:ward:B"]
    assert stored_entities[0]["srid"] == 4326


def test_stored_entity_says_which_source_and_extract_date_it_came_from(
    connection_with_source: DatabaseConnection,
) -> None:
    layer = build_layer([build_ward("A")])

    load_base_layer(connection_with_source, layer)

    source = connection_with_source.execute(SELECT_SOURCE_ID_SQL, [TEST_SOURCE.name]).fetchone()
    assert source is not None
    stored_metadata = select_layer_entities(connection_with_source)[0]["metadata"]
    assert stored_metadata == {
        "area_kind": "ward",
        "base_layer": TEST_LAYER_NAME,
        "source_id": str(source["id"]),
        "observed_at": "2026-10-12",
    }


def test_loading_the_same_layer_twice_keeps_every_entity_id(
    connection_with_source: DatabaseConnection,
) -> None:
    layer = build_layer([build_ward("A"), build_ward("B")])
    load_base_layer(connection_with_source, layer)
    ids_after_first_load = [
        entity["id"] for entity in select_layer_entities(connection_with_source)
    ]

    load_base_layer(connection_with_source, layer)

    ids_after_second_load = [
        entity["id"] for entity in select_layer_entities(connection_with_source)
    ]
    assert ids_after_second_load == ids_after_first_load


def test_reload_updates_a_renamed_entity_in_place(
    connection_with_source: DatabaseConnection,
) -> None:
    load_base_layer(connection_with_source, build_layer([build_ward("A", name="Old name")]))

    load_base_layer(connection_with_source, build_layer([build_ward("A", name="New name")]))

    stored_entities = select_layer_entities(connection_with_source)
    assert len(stored_entities) == 1
    assert stored_entities[0]["name"] == "New name"


def test_entity_missing_from_a_newer_file_is_retired_not_deleted(
    connection_with_source: DatabaseConnection,
) -> None:
    load_base_layer(connection_with_source, build_layer([build_ward("A"), build_ward("B")]))

    result = load_base_layer(connection_with_source, build_layer([build_ward("A")]))

    stored_entities = select_layer_entities(connection_with_source)
    assert result.retired_count == 1
    assert len(stored_entities) == 2
    assert stored_entities[0]["retired_at"] is None
    assert stored_entities[1]["retired_at"] is not None


def test_retired_entity_that_returns_is_active_again_with_the_same_id(
    connection_with_source: DatabaseConnection,
) -> None:
    both_wards = build_layer([build_ward("A"), build_ward("B")])
    load_base_layer(connection_with_source, both_wards)
    id_before_retiring = select_layer_entities(connection_with_source)[1]["id"]
    load_base_layer(connection_with_source, build_layer([build_ward("A")]))

    load_base_layer(connection_with_source, both_wards)

    returned_entity = select_layer_entities(connection_with_source)[1]
    assert returned_entity["retired_at"] is None
    assert returned_entity["id"] == id_before_retiring


def test_loading_one_layer_never_retires_another_layers_entities(
    connection_with_source: DatabaseConnection,
) -> None:
    load_base_layer(
        connection_with_source, build_layer([build_ward("OTHER")], layer_name=OTHER_LAYER_NAME)
    )

    load_base_layer(connection_with_source, build_layer([build_ward("A")]))

    other_entities = select_layer_entities(connection_with_source, OTHER_LAYER_NAME)
    assert other_entities[0]["retired_at"] is None


def test_empty_layer_is_refused_so_a_broken_extract_retires_nothing(
    connection_with_source: DatabaseConnection,
) -> None:
    load_base_layer(connection_with_source, build_layer([build_ward("A")]))

    with pytest.raises(BaseLayerLoadError, match="no entities"):
        load_base_layer(connection_with_source, build_layer([]))

    assert select_layer_entities(connection_with_source)[0]["retired_at"] is None


def test_layer_with_the_same_external_ref_twice_is_refused(
    connection_with_source: DatabaseConnection,
) -> None:
    layer = build_layer([build_ward("A"), build_ward("A")])

    with pytest.raises(BaseLayerLoadError, match="twice"):
        load_base_layer(connection_with_source, layer)

    assert select_layer_entities(connection_with_source) == []


def test_layer_from_a_source_that_is_not_registered_is_refused(
    ingest_connection: DatabaseConnection,
) -> None:
    layer = build_layer([build_ward("A")])

    with pytest.raises(BaseLayerLoadError, match="register_sources"):
        load_base_layer(ingest_connection, layer)


def test_point_entity_drawn_as_an_outline_is_stored_as_a_point_inside_it(
    connection_with_source: DatabaseConnection,
) -> None:
    water_works_outline = build_ward("A").model_copy(
        update={"entity_type": EntityType.POINT, "radius_m": 25.0}
    )

    load_base_layer(connection_with_source, build_layer([water_works_outline]))

    stored_entity = select_layer_entities(connection_with_source)[0]
    assert stored_entity["geometry_type"] == "ST_Point"
    assert 36.85 < stored_entity["longitude"] < 36.86


def build_ward_with_a_broken_outline(ward_code: str) -> EntityRow:
    """A ward whose outline crosses itself like a bow tie, which PostGIS calls invalid."""
    bow_tie = [[[36.85, -1.27], [36.86, -1.28], [36.86, -1.27], [36.85, -1.28], [36.85, -1.27]]]
    return build_ward(ward_code).model_copy(
        update={"geometry": {"type": "Polygon", "coordinates": bow_tie}}
    )


def test_entity_with_a_broken_geometry_is_counted_and_left_out(
    connection_with_source: DatabaseConnection,
) -> None:
    layer = build_layer([build_ward("A"), build_ward_with_a_broken_outline("B")])

    result = load_base_layer(connection_with_source, layer)

    stored_entities = select_layer_entities(connection_with_source)
    assert result.loaded_count == 1
    assert result.skipped_count == 1
    assert [entity["external_ref"] for entity in stored_entities] == ["test:ward:A"]


def test_stored_entity_whose_newer_geometry_is_broken_is_kept_and_not_retired(
    connection_with_source: DatabaseConnection,
) -> None:
    load_base_layer(connection_with_source, build_layer([build_ward("A")]))

    result = load_base_layer(
        connection_with_source, build_layer([build_ward_with_a_broken_outline("A")])
    )

    stored_entities = select_layer_entities(connection_with_source)
    assert result.retired_count == 0
    assert stored_entities[0]["retired_at"] is None
    assert stored_entities[0]["longitude"] is not None
