# Checks the fixture end to end against a migrated database, acting as nv_ingest: entities are
# picked from a ward, written as SQL, and that SQL puts the same entities back.

from typing import Any

from database.connection import DatabaseConnection
from local_fixture.select_fixture_rows import (
    select_area_entities,
    select_nearest_in_ward,
    select_sources,
)
from local_fixture.write_fixture_sql import write_fixture_sql

SELECT_TEST_ENTITIES_SQL = """
    SELECT entity.id::text AS id, entity.name, entity.metadata,
           parent_area.external_ref AS parent_external_ref
    FROM core.entities AS entity
    LEFT JOIN core.entities AS parent_area ON parent_area.id = entity.parent_area_id
    WHERE entity.external_ref LIKE 'test:fixture:%%' AND entity.entity_type = 'point'
    ORDER BY entity.external_ref
"""

WARD_EXTERNAL_REF = "test:fixture:ward"


def select_test_water_points(connection: DatabaseConnection) -> list[dict[str, Any]]:
    """The stored test water points, ordered by external reference."""
    return connection.execute(SELECT_TEST_ENTITIES_SQL).fetchall()


def test_fixture_sql_puts_the_same_entities_back_with_their_ids_and_parents(
    connection_with_ward: DatabaseConnection,
) -> None:
    areas = select_area_entities(connection_with_ward)
    test_wards = [area for area in areas if area.external_ref == WARD_EXTERNAL_REF]
    water_points = select_nearest_in_ward(connection_with_ward, WARD_EXTERNAL_REF, "point", 3)
    ids_before = [row["id"] for row in select_test_water_points(connection_with_ward)]
    sources = select_sources(connection_with_ward, water_points)
    fixture_sql = write_fixture_sql(sources, [test_wards, water_points])

    connection_with_ward.execute("ROLLBACK TO SAVEPOINT before_test_rows")
    connection_with_ward.execute(fixture_sql.encode())

    stored_water_points = select_test_water_points(connection_with_ward)
    assert [row["id"] for row in stored_water_points] == ids_before
    assert stored_water_points[0]["name"] == "Mama Njeri's tap"
    assert stored_water_points[0]["parent_external_ref"] == WARD_EXTERNAL_REF
