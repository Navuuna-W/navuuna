# Loads one entity's evidence from the database as plain JSON rows (ADR-003, Bible §14.6).
# The runner calls this before running the adapters for an entity; build_adapter_inputs then
# hands each adapter only the kinds it declared. This is the only engine file that knows table
# names, and none of them belongs to one module: records tables are found by asking the database.

from uuid import UUID

from psycopg import sql

from engine.adapter_inputs import InputRow, InputRows
from engine.database import DatabaseConnection
from engine.input_kind import InputKind

# Adapters never get raw geometry; nearby queries are done in SQL (Bible §14.6: pure adapters).
LOAD_ENTITY_SQL = """
    SELECT to_jsonb(entity) - 'geom' AS row
    FROM core.entities AS entity
    WHERE entity.id = %(entity_id)s AND entity.retired_at IS NULL
"""

# An observation whose consent was withdrawn must stop counting (Bible §9.2 NFR-06, KDPA).
# Observations with no consent at all (OSM, satellite) have no contributor and stay in.
LOAD_OBSERVATIONS_SQL = """
    SELECT to_jsonb(observation) AS row
    FROM core.observations AS observation
    LEFT JOIN core.consents AS consent ON consent.id = observation.consent_id
    WHERE observation.entity_id = %(entity_id)s AND consent.withdrawn_at IS NULL
    ORDER BY observation.observed_at DESC
"""

LOAD_EO_STATS_SQL = """
    SELECT to_jsonb(eo_stat) AS row
    FROM raw.eo_stats AS eo_stat
    WHERE eo_stat.entity_id = %(entity_id)s
    ORDER BY eo_stat.acquired_at DESC
"""

# Same ward and same module only: a ward can hold thousands of buildings, and no adapter needs
# counts across modules yet. No parent area or no module (shared areas) → no rows.
LOAD_SAME_AREA_ENTITIES_SQL = """
    SELECT to_jsonb(other) - 'geom' AS row
    FROM core.entities AS entity
    JOIN core.entities AS other
      ON other.parent_area_id = entity.parent_area_id AND other.module = entity.module
    WHERE entity.id = %(entity_id)s AND other.id <> entity.id AND other.retired_at IS NULL
    ORDER BY other.id
"""

# Rows for both nearby kinds: other active entities within the radius, nearest first, each with
# its distance in metres (geography). {entity_type_filter} picks the kind's entity types.
# geography distances skip the GIST index on geom; fine at today's size (see K-18).
NEARBY_SQL_TEMPLATE = """
    SELECT (to_jsonb(other) - 'geom') || jsonb_build_object('distance_m', distance.metres) AS row
    FROM core.entities AS entity
    JOIN core.entities AS other ON other.id <> entity.id AND other.retired_at IS NULL
    CROSS JOIN LATERAL (
        SELECT ST_Distance(entity.geom::geography, other.geom::geography) AS metres
    ) AS distance
    WHERE entity.id = %(entity_id)s
      AND {entity_type_filter}
      AND ST_DWithin(entity.geom::geography, other.geom::geography, %(radius_m)s)
    ORDER BY distance.metres, other.id
"""
# OSM ways are loaded as segment entities (D-05).
LOAD_NEARBY_WAYS_SQL = NEARBY_SQL_TEMPLATE.format(
    entity_type_filter="other.entity_type = 'segment'"
)
# Areas are left out: a ward around the entity would always be "0 m away". Wards come
# through same_area_entities instead.
LOAD_NEARBY_ENTITIES_SQL = NEARBY_SQL_TEMPLATE.format(
    entity_type_filter="other.entity_type <> 'area'"
)

# Every records table matched to entities has an entity_id column; the review queue has none.
FIND_RECORDS_TABLES_SQL = """
    SELECT table_name
    FROM information_schema.columns
    WHERE table_schema = 'records' AND column_name = 'entity_id'
    ORDER BY table_name
"""

# Newest id first: ids are UUIDv7, so they sort by insert time (ADR-012).
LOAD_RECORDS_SQL = """
    SELECT to_jsonb(record) || jsonb_build_object('record_table', {table_name}) AS row
    FROM {table} AS record
    WHERE record.entity_id = %(entity_id)s
    ORDER BY record.id DESC
"""


# Kinds loaded with one query on the entity id. RECORDS and the nearby kinds need more.
ENTITY_QUERIES = {
    InputKind.OBSERVATIONS: LOAD_OBSERVATIONS_SQL,
    InputKind.EO_STATS: LOAD_EO_STATS_SQL,
    InputKind.SAME_AREA_ENTITIES: LOAD_SAME_AREA_ENTITIES_SQL,
}
NEARBY_QUERIES = {
    InputKind.NEARBY_WAYS: LOAD_NEARBY_WAYS_SQL,
    InputKind.NEARBY_ENTITIES: LOAD_NEARBY_ENTITIES_SQL,
}


class MissingRadiusError(Exception):
    """Nearby rows were asked for without a radius; AdapterSpec should have prevented it."""


def load_entity(connection: DatabaseConnection, entity_id: UUID) -> InputRow | None:
    """Return the entity's core.entities row without its geometry.

    Output: the row as JSON, or None when the entity does not exist or is retired —
    the runner skips retired entities (K-09 work pack, E15).
    """
    found = connection.execute(LOAD_ENTITY_SQL, {"entity_id": entity_id}).fetchone()
    if found is None:
        return None
    entity_row: InputRow = found["row"]
    return entity_row


def load_input_rows(
    connection: DatabaseConnection,
    entity_id: UUID,
    kinds: frozenset[InputKind],
    nearby_radius_m: float | None = None,
) -> dict[InputKind, InputRows]:
    """Load the rows of each requested kind for one entity.

    Inputs: an open connection, the entity id, the kinds the entity's adapters declare and,
    for the nearby kinds, the largest radius any of them asked for. Output: rows by kind;
    nearby rows are nearest first and carry distance_m. ENTITY is skipped; load_entity
    returns it. Raises MissingRadiusError for a nearby kind without a radius.
    Implements ADR-003 — the runner loads exactly what `requires` declares.
    """
    loaded_rows: dict[InputKind, InputRows] = {}
    for kind in sorted(kinds - {InputKind.ENTITY}):
        loaded_rows[kind] = load_rows_of_kind(connection, entity_id, kind, nearby_radius_m)
    return loaded_rows


def load_rows_of_kind(
    connection: DatabaseConnection,
    entity_id: UUID,
    kind: InputKind,
    nearby_radius_m: float | None,
) -> InputRows:
    """Load one kind of input for one entity."""
    if kind is InputKind.RECORDS:
        return load_records(connection, entity_id)
    if kind in NEARBY_QUERIES:
        return load_nearby_rows(connection, entity_id, NEARBY_QUERIES[kind], nearby_radius_m)
    return fetch_rows(connection, ENTITY_QUERIES[kind], entity_id)


def load_nearby_rows(
    connection: DatabaseConnection, entity_id: UUID, query: str, nearby_radius_m: float | None
) -> InputRows:
    """Load the rows of one nearby kind within nearby_radius_m, each with its distance_m."""
    if nearby_radius_m is None:
        raise MissingRadiusError("nearby inputs need a radius (AdapterSpec.nearby_radius_m)")
    found_rows = connection.execute(
        query, {"entity_id": entity_id, "radius_m": nearby_radius_m}
    ).fetchall()
    return tuple(found_row["row"] for found_row in found_rows)


def load_records(connection: DatabaseConnection, entity_id: UUID) -> InputRows:
    """Load the entity's rows from every records table, each tagged with its table name."""
    records: list[InputRow] = []
    for table_row in connection.execute(FIND_RECORDS_TABLES_SQL).fetchall():
        table_name = table_row["table_name"]
        query = sql.SQL(LOAD_RECORDS_SQL).format(
            table=sql.Identifier("records", table_name), table_name=sql.Literal(table_name)
        )
        records.extend(fetch_rows(connection, query, entity_id))
    return tuple(records)


def fetch_rows(
    connection: DatabaseConnection, query: str | sql.Composed, entity_id: UUID
) -> InputRows:
    """Run a query whose single column `row` is JSON and return those rows."""
    found_rows = connection.execute(query, {"entity_id": entity_id}).fetchall()
    return tuple(found_row["row"] for found_row in found_rows)
