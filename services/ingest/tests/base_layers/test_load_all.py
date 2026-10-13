# Checks the command that loads every base layer from a folder of layer files, against a
# migrated database and acting as nv_ingest: all five layers arrive, entities are linked to
# their parent areas, and a second run changes nothing.

import json
from datetime import date
from pathlib import Path
from typing import Any

import pytest

from base_layers.load_all import (
    COUNTY_SOURCE_NAME,
    OSM_SOURCE_NAME,
    WARDS_SOURCE_NAME,
    load_all_layers,
    main,
)
from database.connection import DatabaseConnection
from sources.register_sources import register_sources
from sources.source_definition import SourceDefinition
from sources.source_kind import SourceKind

SELECT_ENTITIES_SQL = """
    SELECT entity.external_ref, entity.entity_type, entity.module,
           parent_area.external_ref AS parent_external_ref
    FROM core.entities AS entity
    LEFT JOIN core.entities AS parent_area ON parent_area.id = entity.parent_area_id
    WHERE entity.external_ref = ANY(%s)
"""

OSM_EXTRACT_DATE = date(2026, 10, 12)

# One square ward inside one square county; the OSM features below sit inside the ward.
WARD_SQUARE = [[[36.85, -1.27], [36.86, -1.27], [36.86, -1.28], [36.85, -1.28], [36.85, -1.27]]]
COUNTY_SQUARE = [[[36.8, -1.2], [36.9, -1.2], [36.9, -1.3], [36.8, -1.3], [36.8, -1.2]]]
BUILDING_SQUARE = [
    [[36.851, -1.271], [36.852, -1.271], [36.852, -1.272], [36.851, -1.272], [36.851, -1.271]]
]

LAYER_FILES: dict[str, list[dict[str, Any]]] = {
    "nairobi_county.geojsonl": [
        {
            "properties": {"adm1_name": "Test county", "adm1_pcode": "TEST047"},
            "geometry": {"type": "Polygon", "coordinates": COUNTY_SQUARE},
        }
    ],
    "nairobi_wards.geojsonl": [
        {
            "properties": {"IEBC_WARDS": "Test ward", "PCODE": "TEST0001"},
            "geometry": {"type": "Polygon", "coordinates": WARD_SQUARE},
        }
    ],
    "osm_water_point_nodes.geojsonl": [
        {
            "properties": {"osm_id": "9000000001", "man_made": "water_tap"},
            "geometry": {"type": "Point", "coordinates": [36.855, -1.275]},
        }
    ],
    "osm_water_point_outlines.geojsonl": [
        {
            "properties": {"osm_way_id": "9000000002", "man_made": "water_works"},
            "geometry": {"type": "MultiPolygon", "coordinates": [BUILDING_SQUARE]},
        }
    ],
    "osm_roads.geojsonl": [
        {
            "properties": {"osm_id": "9000000003", "highway": "residential"},
            "geometry": {"type": "LineString", "coordinates": [[36.853, -1.273], [36.854, -1.273]]},
        }
    ],
    "osm_buildings.geojsonl": [
        {
            "properties": {"osm_way_id": "9000000004", "building": "yes"},
            "geometry": {"type": "MultiPolygon", "coordinates": [BUILDING_SQUARE]},
        }
    ],
}

EXPECTED_EXTERNAL_REFS = [
    "iebc:county:TEST047",
    "iebc:ward:TEST0001",
    "osm:n9000000001",
    "osm:w9000000002",
    "osm:w9000000003",
    "osm:w9000000004",
]


def write_layer_files(layer_folder: Path) -> None:
    """Write every layer file the extract script would write, one feature per line."""
    for file_name, features in LAYER_FILES.items():
        lines = [json.dumps({"type": "Feature", **feature}) for feature in features]
        (layer_folder / file_name).write_text("\n".join(lines) + "\n")


def select_entities(connection: DatabaseConnection) -> dict[str, dict[str, Any]]:
    """The test entities that are stored, by external reference."""
    rows = connection.execute(SELECT_ENTITIES_SQL, [EXPECTED_EXTERNAL_REFS]).fetchall()
    return {row["external_ref"]: row for row in rows}


@pytest.fixture
def connection_with_sources(ingest_connection: DatabaseConnection) -> DatabaseConnection:
    """The nv_ingest connection with the three sources the layers come from."""
    source_names = [COUNTY_SOURCE_NAME, WARDS_SOURCE_NAME, OSM_SOURCE_NAME]
    sources = [
        SourceDefinition(
            name=name, kind=SourceKind.VECTOR, licence="Test licence", attribution="Test credit"
        )
        for name in source_names
    ]
    register_sources(ingest_connection, sources)
    return ingest_connection


def test_loading_a_layer_folder_stores_every_layer_with_its_entity_type(
    connection_with_sources: DatabaseConnection, tmp_path: Path
) -> None:
    write_layer_files(tmp_path)

    loaded_count = load_all_layers(connection_with_sources, tmp_path, OSM_EXTRACT_DATE)

    entities = select_entities(connection_with_sources)
    assert loaded_count == len(EXPECTED_EXTERNAL_REFS)
    assert entities["iebc:ward:TEST0001"]["entity_type"] == "area"
    assert entities["osm:n9000000001"]["entity_type"] == "point"
    assert entities["osm:w9000000002"]["module"] == "water"
    assert entities["osm:w9000000003"]["entity_type"] == "segment"
    assert entities["osm:w9000000004"]["module"] == "land"


def test_loaded_entities_are_linked_to_the_area_they_sit_in(
    connection_with_sources: DatabaseConnection, tmp_path: Path
) -> None:
    write_layer_files(tmp_path)

    load_all_layers(connection_with_sources, tmp_path, OSM_EXTRACT_DATE)

    entities = select_entities(connection_with_sources)
    assert entities["iebc:county:TEST047"]["parent_external_ref"] is None
    assert entities["iebc:ward:TEST0001"]["parent_external_ref"] == "iebc:county:TEST047"
    assert entities["osm:n9000000001"]["parent_external_ref"] == "iebc:ward:TEST0001"
    assert entities["osm:w9000000003"]["parent_external_ref"] == "iebc:ward:TEST0001"


def test_loading_the_same_folder_twice_adds_no_entities(
    connection_with_sources: DatabaseConnection, tmp_path: Path
) -> None:
    write_layer_files(tmp_path)
    load_all_layers(connection_with_sources, tmp_path, OSM_EXTRACT_DATE)

    load_all_layers(connection_with_sources, tmp_path, OSM_EXTRACT_DATE)

    assert len(select_entities(connection_with_sources)) == len(EXPECTED_EXTERNAL_REFS)


def test_folder_with_a_missing_layer_file_stops_the_load(
    connection_with_sources: DatabaseConnection, tmp_path: Path
) -> None:
    write_layer_files(tmp_path)
    (tmp_path / "osm_roads.geojsonl").unlink()

    with pytest.raises(FileNotFoundError):
        load_all_layers(connection_with_sources, tmp_path, OSM_EXTRACT_DATE)


def test_command_with_the_wrong_arguments_prints_how_to_use_it(
    capsys: pytest.CaptureFixture[str],
) -> None:
    exit_code = main(["only-one-argument"])

    assert exit_code == 1
    assert "usage" in capsys.readouterr().err


def test_command_with_a_date_that_is_not_a_date_exits_with_an_error(
    capsys: pytest.CaptureFixture[str],
) -> None:
    exit_code = main(["some-folder", "yesterday"])

    assert exit_code == 1
    assert "Cannot load base layers" in capsys.readouterr().err
