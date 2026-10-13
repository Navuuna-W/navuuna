# Picks the entities of the local fixture (work pack D-14) out of a database that holds the
# full Nairobi base layers (D-05). Runs before write_fixture_sql.py. The rule is fixed, so
# building the fixture again from the same load gives the same entities.

from collections.abc import Sequence

from database.connection import DatabaseConnection
from local_fixture.fixture_rows import FixtureEntity, FixtureSource

# Metadata the fixture leaves out. `operator` can hold a person's name (the fixture carries
# no personal data); `source_id` is looked up again when the fixture is loaded.
METADATA_KEYS_LEFT_OUT = ("operator", "source_id")

# Ward and county outlines are simplified to about 5 metres (in degrees) and written with 6
# decimal places, which keeps the fixture file well under the 1 MB limit (Bible §14.3).
AREA_SIMPLIFY_TOLERANCE_DEGREES = 0.00005
COORDINATE_DECIMAL_PLACES = 6
# One step of the 6th decimal place. Simplifying and rounding can make an outline cross
# itself; ST_ReducePrecision snaps it to this grid and keeps it a valid shape.
COORDINATE_GRID_DEGREES = 0.000001

# The columns every query below returns, in the shape of FixtureEntity.
ENTITY_COLUMNS_SQL = """
    entity.id::text AS id, entity.entity_type, entity.module, entity.name, entity.external_ref,
    entity.radius_m, entity.metadata - %(metadata_keys_left_out)s::text[] AS metadata,
    parent_area.external_ref AS parent_external_ref, source.name AS source_name
"""

ENTITY_JOINS_SQL = """
    JOIN core.sources AS source ON source.id = (entity.metadata ->> 'source_id')::uuid
    LEFT JOIN core.entities AS parent_area ON parent_area.id = entity.parent_area_id
"""

# Parents come first (the county has no parent), so the file can load areas in this order.
SELECT_AREAS_SQL = f"""
    SELECT {ENTITY_COLUMNS_SQL},
           public.ST_AsText(
               public.ST_ReducePrecision(
                   public.ST_SimplifyPreserveTopology(entity.geom, %(simplify_tolerance)s),
                   %(coordinate_grid)s
               ),
               %(decimal_places)s
           ) AS geometry_wkt
    FROM core.entities AS entity
    {ENTITY_JOINS_SQL}
    WHERE entity.entity_type = 'area' AND entity.retired_at IS NULL
    ORDER BY (entity.parent_area_id IS NOT NULL), entity.external_ref
"""

# The entities of one type nearest the middle of a ward: a tight cluster, so the fixture's
# water points have neighbours and roads close by, as the water adapters need.
SELECT_NEAREST_IN_WARD_SQL = f"""
    SELECT {ENTITY_COLUMNS_SQL},
           public.ST_AsText(entity.geom, %(decimal_places)s) AS geometry_wkt
    FROM core.entities AS entity
    {ENTITY_JOINS_SQL}
    WHERE parent_area.external_ref = %(ward_external_ref)s
      AND entity.entity_type = %(entity_type)s
      AND entity.retired_at IS NULL
    ORDER BY public.ST_Distance(entity.geom, public.ST_PointOnSurface(parent_area.geom)),
             entity.external_ref
    LIMIT %(entity_count)s
"""

SELECT_SOURCES_SQL = """
    SELECT name, kind, base_url, licence, attribution
    FROM core.sources
    WHERE name = ANY(%(source_names)s)
    ORDER BY name
"""


def select_area_entities(connection: DatabaseConnection) -> list[FixtureEntity]:
    """Return the county and every ward, with simplified outlines, parents first."""
    rows = connection.execute(
        SELECT_AREAS_SQL,
        {
            "metadata_keys_left_out": list(METADATA_KEYS_LEFT_OUT),
            "simplify_tolerance": AREA_SIMPLIFY_TOLERANCE_DEGREES,
            "coordinate_grid": COORDINATE_GRID_DEGREES,
            "decimal_places": COORDINATE_DECIMAL_PLACES,
        },
    ).fetchall()
    return [FixtureEntity.model_validate(row) for row in rows]


def select_nearest_in_ward(
    connection: DatabaseConnection, ward_external_ref: str, entity_type: str, entity_count: int
) -> list[FixtureEntity]:
    """Return the entities of one type nearest the middle of a ward.

    Inputs: the ward's external reference, the entity type (`point`, `segment` or `parcel`)
    and how many to take. Output: at most that many entities, nearest first.
    """
    rows = connection.execute(
        SELECT_NEAREST_IN_WARD_SQL,
        {
            "metadata_keys_left_out": list(METADATA_KEYS_LEFT_OUT),
            "decimal_places": COORDINATE_DECIMAL_PLACES,
            "ward_external_ref": ward_external_ref,
            "entity_type": entity_type,
            "entity_count": entity_count,
        },
    ).fetchall()
    return [FixtureEntity.model_validate(row) for row in rows]


def select_sources(
    connection: DatabaseConnection, entities: Sequence[FixtureEntity]
) -> list[FixtureSource]:
    """Return the core.sources rows that the given entities came from."""
    source_names = sorted({entity.source_name for entity in entities})
    rows = connection.execute(SELECT_SOURCES_SQL, {"source_names": source_names}).fetchall()
    return [FixtureSource.model_validate(row) for row in rows]
