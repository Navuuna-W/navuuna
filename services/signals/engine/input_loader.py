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


class UnsupportedInputError(Exception):
    """An adapter asked for an input kind the loader cannot load yet."""


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
    connection: DatabaseConnection, entity_id: UUID, kinds: frozenset[InputKind]
) -> dict[InputKind, InputRows]:
    """Load the rows of each requested kind for one entity, newest first.

    Inputs: an open connection, the entity id and the kinds the entity's adapters declare.
    Output: rows by kind. ENTITY is skipped here; load_entity returns it.
    Raises UnsupportedInputError for eo_stats and the nearby kinds, which K-09b-2d adds.
    Implements ADR-003 — the runner loads exactly what `requires` declares.
    """
    loaded_rows: dict[InputKind, InputRows] = {}
    for kind in sorted(kinds - {InputKind.ENTITY}):
        loaded_rows[kind] = load_rows_of_kind(connection, entity_id, kind)
    return loaded_rows


def load_rows_of_kind(
    connection: DatabaseConnection, entity_id: UUID, kind: InputKind
) -> InputRows:
    """Load one kind of input for one entity."""
    if kind is InputKind.OBSERVATIONS:
        return fetch_rows(connection, LOAD_OBSERVATIONS_SQL, entity_id)
    if kind is InputKind.RECORDS:
        return load_records(connection, entity_id)
    raise UnsupportedInputError(f"the runner cannot load {kind.value!r} inputs yet")


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
