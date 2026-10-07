# Picks which entities a module run scores, one page at a time (K-09 work pack: batch by
# module × entity chunk). module_run.py asks for the next page until none are left.

from uuid import UUID

from engine.database import DatabaseConnection
from engine.entity_type import EntityType

# Keyset paging on id: each page starts after the last id of the one before, so rows added
# during a run never shift a page, and ids are UUIDv7 so the order follows insert time.
FIND_ENTITY_IDS_SQL = """
    SELECT id
    FROM core.entities
    WHERE module = %(module)s
      AND entity_type = ANY(%(entity_types)s)
      AND retired_at IS NULL
      AND (%(after_id)s::uuid IS NULL OR id > %(after_id)s::uuid)
    ORDER BY id
    LIMIT %(limit)s
"""


def find_entity_ids(
    connection: DatabaseConnection,
    module: str,
    entity_types: frozenset[EntityType],
    after_id: UUID | None,
    limit: int,
) -> list[UUID]:
    """Return the next page of entity ids a module's adapters should score.

    Inputs: the module name, the entity types its adapters score, the last id of the previous
    page (None for the first page) and the page size. Output: up to `limit` ids of entities
    that are not retired, belong to the module and have one of the types, in id order.
    Shared areas (module is null) belong to no module, so no module run picks them.
    Implements the K-09 work pack (batching) and E15 (retired entities are skipped).
    """
    found_rows = connection.execute(
        FIND_ENTITY_IDS_SQL,
        {
            "module": module,
            # Plain strings: psycopg would send an Enum by its name ("POINT"), not its value.
            "entity_types": sorted(entity_type.value for entity_type in entity_types),
            "after_id": after_id,
            "limit": limit,
        },
    ).fetchall()
    return [found_row["id"] for found_row in found_rows]
