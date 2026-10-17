# Shared pytest fixtures for the local-fixture tests: one test ward with three water points
# in a migrated database, written as nv_ingest and rolled back after the test.

import pytest

from database.connection import DatabaseConnection

INSERT_SOURCE_SQL = """
    INSERT INTO core.sources (name, kind, licence, attribution)
    VALUES ('A test fixture source', 'vector', 'Test licence', 'Test credit')
    RETURNING id
"""
INSERT_WARD_SQL = """
    INSERT INTO core.entities (entity_type, name, external_ref, geom, metadata)
    VALUES ('area', 'Test ward', 'test:fixture:ward',
            'SRID=4326;POLYGON((36.85 -1.27,36.86 -1.27,36.86 -1.28,36.85 -1.28,36.85 -1.27))',
            jsonb_build_object('source_id', %(source_id)s::text))
    RETURNING id
"""
# Three water points on a line through the ward; `middle` is nearest the ward's centre.
INSERT_WATER_POINTS_SQL = """
    INSERT INTO core.entities
        (entity_type, module, name, external_ref, geom, radius_m, parent_area_id, metadata)
    SELECT 'point', 'water', 'Mama Njeri''s tap', 'test:fixture:' || place.reference,
           public.ST_SetSRID(public.ST_MakePoint(place.longitude, -1.275), 4326), 25, %(ward_id)s,
           jsonb_build_object('source_id', %(source_id)s::text, 'operator', 'A person')
    FROM (VALUES ('middle', 36.855), ('near', 36.857), ('far', 36.859))
         AS place (reference, longitude)
"""


@pytest.fixture
def connection_with_ward(ingest_connection: DatabaseConnection) -> DatabaseConnection:
    """The nv_ingest connection with one test ward holding three water points.

    A savepoint named `before_test_rows` is set first, so a test can remove the rows again
    and load them back from the fixture SQL.
    """
    ingest_connection.execute("SAVEPOINT before_test_rows")
    source = ingest_connection.execute(INSERT_SOURCE_SQL).fetchone()
    assert source is not None
    ward = ingest_connection.execute(INSERT_WARD_SQL, {"source_id": source["id"]}).fetchone()
    assert ward is not None
    ingest_connection.execute(
        INSERT_WATER_POINTS_SQL, {"ward_id": ward["id"], "source_id": source["id"]}
    )
    return ingest_connection
