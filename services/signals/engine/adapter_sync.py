# Keeps core.adapters in step with the adapters the registry found (ADR-003, K-09).
# Runs at the start of every signal run, after discover_adapters and before any scoring:
# each score row points at its core.adapters row, so the row must exist first.

from uuid import UUID

from engine.database import DatabaseConnection
from engine.registered_adapter import RegisteredAdapter

# One row per sub-variable × version. A new version is a new row, so old score rows keep
# pointing at the adapter version that produced them.
UPSERT_ADAPTER_SQL = """
    INSERT INTO core.adapters
        (module, sub_id, version, enabled, signal_description, code_ref, entity_types)
    VALUES (%(module)s, %(sub_id)s, %(version)s, true, %(signal_description)s,
            %(code_ref)s, %(entity_types)s)
    ON CONFLICT (sub_id, version) DO UPDATE SET
        module = EXCLUDED.module,
        enabled = true,
        signal_description = EXCLUDED.signal_description,
        code_ref = EXCLUDED.code_ref,
        entity_types = EXCLUDED.entity_types
    RETURNING id
"""

# Rows are switched off, never deleted: no role may DELETE (ADR-004a §2), and old score
# rows still point at them.
DISABLE_OTHER_ADAPTERS_SQL = """
    UPDATE core.adapters
    SET enabled = false
    WHERE enabled AND NOT (id = ANY(%(kept_adapter_ids)s))
"""


def sync_adapters(
    connection: DatabaseConnection, adapters: list[RegisteredAdapter]
) -> dict[str, UUID]:
    """Write one core.adapters row per adapter and switch off every other row.

    Inputs: an open connection and the registry's adapters. Output: the core.adapters id
    of each adapter, by sub_id — the runner stores it on every score row. Rows for an old
    version, a deleted adapter or a switched-off module end up enabled = false.
    Runs in one transaction (a savepoint if the caller already opened one).
    Implements ADR-003 and the K-09 work pack — the registry syncs core.adapters.
    """
    with connection.transaction():
        adapter_ids = {
            adapter.spec.sub_id: upsert_adapter(connection, adapter) for adapter in adapters
        }
        connection.execute(
            DISABLE_OTHER_ADAPTERS_SQL, {"kept_adapter_ids": list(adapter_ids.values())}
        )
    return adapter_ids


def upsert_adapter(connection: DatabaseConnection, adapter: RegisteredAdapter) -> UUID:
    """Insert or refresh one adapter's row and return its id."""
    spec = adapter.spec
    row = connection.execute(
        UPSERT_ADAPTER_SQL,
        {
            "module": spec.module,
            "sub_id": spec.sub_id,
            "version": spec.version,
            "signal_description": spec.signal_description,
            "code_ref": adapter.code_ref,
            # Plain strings: psycopg would store an Enum by its name ("POINT"), not its value.
            "entity_types": sorted(entity_type.value for entity_type in spec.entity_types),
        },
    ).fetchone()
    # RETURNING always gives exactly one row for a single-row INSERT ... ON CONFLICT DO UPDATE.
    assert row is not None
    adapter_id: UUID = row["id"]
    return adapter_id
