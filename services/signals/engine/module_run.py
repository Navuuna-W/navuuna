# Scores every entity of one module, chunk by chunk, and tells the rollup after each chunk
# (K-09 work pack, ADR-004a §3). The CLI (`python -m engine run`) calls this once per module.

from dataclasses import dataclass
from datetime import UTC, datetime
from uuid import UUID

from redis import Redis

from engine.batch_publisher import (
    BATCH_WRITTEN_STREAM,
    MAX_ENTITY_IDS_PER_MESSAGE,
    publish_batch_written,
)
from engine.database import DatabaseConnection
from engine.entity_selection import find_entity_ids
from engine.entity_type import EntityType
from engine.registered_adapter import RegisteredAdapter
from engine.runner import run_entities

# One chunk = one transaction = one batch_written message, so a chunk is never bigger than a
# message may be (ADR-004a §3: at most 500 entity ids).
DEFAULT_CHUNK_SIZE = MAX_ENTITY_IDS_PER_MESSAGE

# The batch id is a UUIDv7 made by the database, so Python and PHP make the same kind of
# id (ADR-012). Python 3.12 has no uuid7().
NEW_BATCH_ID_SQL = "SELECT public.uuid_generate_v7() AS batch_id"


@dataclass(frozen=True)
class ModuleRunSummary:
    """What one module run did: its batch id and how many entities got new score rows."""

    batch_id: UUID
    scored_entity_count: int


def run_module(
    connection: DatabaseConnection,
    redis_client: Redis,
    module: str,
    adapters: list[RegisteredAdapter],
    adapter_ids: dict[str, UUID],
    as_of: datetime,
    chunk_size: int = DEFAULT_CHUNK_SIZE,
    stream: str = BATCH_WRITTEN_STREAM,
) -> ModuleRunSummary:
    """Score every entity of `module` with its adapters, one committed chunk at a time.

    Inputs: open DB and Redis connections, the module, every registered adapter (only this
    module's are used), their core.adapters ids and the run's as_of. After each chunk
    commits, one signals.batch_written message lists the entities that got rows; every
    message of the run shares one batch id. A chunk is committed before it is published,
    so a lost message only delays the rollup until the reconciliation sweep (ADR-004a §4).
    Implements the K-09 work pack — batch by module × entity chunk, publish batch_written.
    """
    module_adapters = [adapter for adapter in adapters if adapter.spec.module == module]
    entity_types = scored_entity_types(module_adapters)
    adapter_versions = {adapter.spec.sub_id: adapter.spec.version for adapter in module_adapters}
    batch_id = create_batch_id(connection)
    scored_entity_count = 0
    last_entity_id: UUID | None = None

    while True:
        entity_ids = find_entity_ids(connection, module, entity_types, last_entity_id, chunk_size)
        if not entity_ids:
            break
        scored_ids = run_entities(connection, module_adapters, adapter_ids, entity_ids, as_of)
        if scored_ids:
            written_at = datetime.now(UTC)
            publish_batch_written(
                redis_client, batch_id, scored_ids, module, adapter_versions, written_at, stream
            )
        scored_entity_count += len(scored_ids)
        last_entity_id = entity_ids[-1]
    return ModuleRunSummary(batch_id=batch_id, scored_entity_count=scored_entity_count)


def scored_entity_types(adapters: list[RegisteredAdapter]) -> frozenset[EntityType]:
    """Every entity type at least one of these adapters scores."""
    entity_types: set[EntityType] = set()
    for adapter in adapters:
        entity_types.update(adapter.spec.entity_types)
    return frozenset(entity_types)


def create_batch_id(connection: DatabaseConnection) -> UUID:
    """Ask the database for a new UUIDv7 to use as the run's batch id."""
    found = connection.execute(NEW_BATCH_ID_SQL).fetchone()
    # A SELECT of one function call always returns exactly one row.
    assert found is not None
    batch_id: UUID = found["batch_id"]
    return batch_id
