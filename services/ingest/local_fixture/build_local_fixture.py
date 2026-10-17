# Builds the local fixture file (work pack D-14) from a database that holds the full Nairobi
# base layers (D-05):
#     python -m local_fixture.build_local_fixture ../../data/fixtures/nairobi_500_entities.sql
# Run it again after a newer base-layer load, then commit the changed file.

import os
import sys
from pathlib import Path

from database.connection import DatabaseConnection, DatabaseSettingsError, connect_to_database
from local_fixture.fixture_rows import FixtureEntity
from local_fixture.select_fixture_rows import (
    select_area_entities,
    select_nearest_in_ward,
    select_sources,
)
from local_fixture.write_fixture_sql import write_fixture_sql

# The two wards the fixture's water points come from: Lindi (Kibera) and Mabatini (Mathare).
# Both are densely mapped, so each water point has neighbours and a road close by.
FOCUS_WARD_EXTERNAL_REFS = ("iebc:ward:KE0735", "iebc:ward:KE0774")

# How many entities of each type to take from each focus ward. With the 85 areas this gives
# the 500 entities of Bible §14.11: 85 + 2 x (150 + 50) + 8 + 7.
WATER_POINTS_PER_WARD = 150
ROADS_PER_WARD = 50
BUILDINGS_PER_WARD = (8, 7)

EXIT_OK = 0
EXIT_ERROR = 1
USAGE = "usage: python -m local_fixture.build_local_fixture <fixture file to write>"


def build_fixture_sql(connection: DatabaseConnection) -> str:
    """Pick the fixture's entities from the database and return the fixture file's SQL.

    Input: a connection to a database with the base layers loaded. Output: the SQL text.
    Implements Bible §14.11 (a seeded 500-entity Nairobi subset).
    """
    area_entities = select_area_entities(connection)
    ward_entities: list[FixtureEntity] = []
    for ward_external_ref, building_count in zip(
        FOCUS_WARD_EXTERNAL_REFS, BUILDINGS_PER_WARD, strict=True
    ):
        ward_entities += select_nearest_in_ward(
            connection, ward_external_ref, "point", WATER_POINTS_PER_WARD
        )
        ward_entities += select_nearest_in_ward(
            connection, ward_external_ref, "segment", ROADS_PER_WARD
        )
        ward_entities += select_nearest_in_ward(
            connection, ward_external_ref, "parcel", building_count
        )
    counties = [entity for entity in area_entities if entity.parent_external_ref is None]
    wards = [entity for entity in area_entities if entity.parent_external_ref is not None]
    sources = select_sources(connection, area_entities + ward_entities)
    return write_fixture_sql(sources, [counties, wards, ward_entities])


def main(arguments: list[str]) -> int:
    """Write the fixture file named on the command line. Output: the process exit code."""
    if len(arguments) != 1:
        print(USAGE, file=sys.stderr)
        return EXIT_ERROR
    try:
        connection = connect_to_database(os.environ)
    except DatabaseSettingsError as error:
        print(f"Cannot build the fixture: {error}", file=sys.stderr)
        return EXIT_ERROR
    with connection:
        fixture_sql = build_fixture_sql(connection)
    Path(arguments[0]).write_text(fixture_sql, encoding="utf-8")
    print(f"Wrote {arguments[0]}")
    return EXIT_OK


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
