# Runs the adapters for entities and stores their results (FR-06, ADR-003, Bible §14.6).
# Flow per entity: load the entity → pick the adapters for its type → load what they declare
# → call each pure adapter in dependency order → append each valid result. Part 3 calls this
# from the CLI and the recompute stream, then tells the rollup which entities changed.

import logging
from datetime import datetime
from uuid import UUID

from engine.adapter_inputs import AdapterInputs, InputRow, build_adapter_inputs
from engine.database import DatabaseConnection
from engine.input_kind import InputKind
from engine.input_loader import load_entity, load_input_rows
from engine.registered_adapter import RegisteredAdapter
from engine.score_result import ScoreResult
from engine.score_writer import write_score

logger = logging.getLogger(__name__)


def run_entities(
    connection: DatabaseConnection,
    adapters: list[RegisteredAdapter],
    adapter_ids: dict[str, UUID],
    entity_ids: list[UUID],
    as_of: datetime,
) -> list[UUID]:
    """Score a chunk of entities in one transaction.

    Inputs: an open connection, the registry's adapters (in dependency order), their
    core.adapters ids by sub_id (from sync_adapters), the entity ids and the run's as_of.
    Output: the ids of the entities that got at least one score row — the ones the rollup
    must hear about. A database error rolls the whole chunk back.
    Implements the K-09 work pack — batch by module × entity chunk.
    """
    scored_entity_ids: list[UUID] = []
    with connection.transaction():
        for entity_id in entity_ids:
            if run_entity(connection, adapters, adapter_ids, entity_id, as_of):
                scored_entity_ids.append(entity_id)
    return scored_entity_ids


def run_entity(
    connection: DatabaseConnection,
    adapters: list[RegisteredAdapter],
    adapter_ids: dict[str, UUID],
    entity_id: UUID,
    as_of: datetime,
) -> bool:
    """Score one entity with every adapter that fits its type; return True if a row was written.

    A retired or missing entity is skipped (E15). The caller owns the transaction.
    Implements FR-06 and ADR-003 — the runner loads exactly what `requires` declares.
    """
    entity = load_entity(connection, entity_id)
    if entity is None:
        return False
    fitting_adapters = select_adapters_for_entity(adapters, entity)
    loaded_rows = load_input_rows(connection, entity_id, declared_kinds(fitting_adapters))

    results_so_far: dict[str, ScoreResult] = {}
    for adapter in fitting_adapters:
        inputs = build_adapter_inputs(adapter.spec, as_of, entity, loaded_rows, results_so_far)
        result = call_adapter(adapter, inputs, entity_id)
        if result is None:
            continue
        spec = adapter.spec
        write_score(
            connection, entity_id, spec.sub_id, adapter_ids[spec.sub_id], spec.version, result
        )
        results_so_far[spec.sub_id] = result
    return bool(results_so_far)


def select_adapters_for_entity(
    adapters: list[RegisteredAdapter], entity: InputRow
) -> list[RegisteredAdapter]:
    """Keep the adapters that score this entity's type, in their dependency order."""
    entity_type = entity["entity_type"]
    return [adapter for adapter in adapters if entity_type in adapter.spec.entity_types]


def declared_kinds(adapters: list[RegisteredAdapter]) -> frozenset[InputKind]:
    """Every input kind any of these adapters declares, so each kind is loaded once."""
    kinds: set[InputKind] = set()
    for adapter in adapters:
        kinds.update(adapter.spec.requires)
    return frozenset(kinds)


def call_adapter(
    adapter: RegisteredAdapter, inputs: AdapterInputs, entity_id: UUID
) -> ScoreResult | None:
    """Call one adapter and return its result, or None if it failed.

    Any exception — including a ScoreResult that failed validation — is logged and the
    result dropped: one broken adapter must not stop the run for every other adapter and
    entity. Nothing is written in its place; a made-up null_reason would reach users.
    Implements the K-09 work pack — the runner rejects invalid results.
    """
    try:
        result: object = adapter.score(inputs)
    except Exception:
        logger.exception("adapter %s failed for entity %s", adapter.code_ref, entity_id)
        return None
    if not isinstance(result, ScoreResult):
        logger.error(
            "adapter %s returned %s instead of a ScoreResult for entity %s",
            adapter.code_ref,
            type(result).__name__,
            entity_id,
        )
        return None
    return result
