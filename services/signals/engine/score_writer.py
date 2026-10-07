# Appends one adapter result to scores.sub_variable_scores (FR-06, ADR-004a).
# The runner calls this for every valid ScoreResult. Rows are never updated: a newer row
# replaces an older one for the rollup, and history stays for provenance.

from uuid import UUID

from psycopg.types.json import Jsonb

from engine.database import DatabaseConnection
from engine.score_result import ScoreResult

# computed_at is left to the database default, so every row in one chunk shares its clock.
INSERT_SCORE_SQL = """
    INSERT INTO scores.sub_variable_scores
        (entity_id, sub_id, value, unit, score, confidence, status, null_reason,
         observed_at, source_ids, adapter_id, adapter_version)
    VALUES
        (%(entity_id)s, %(sub_id)s, %(value)s, %(unit)s, %(score)s, %(confidence)s,
         %(status)s, %(null_reason)s, %(observed_at)s, %(source_ids)s, %(adapter_id)s,
         %(adapter_version)s)
"""


def write_score(
    connection: DatabaseConnection,
    entity_id: UUID,
    sub_id: str,
    adapter_id: UUID,
    adapter_version: str,
    result: ScoreResult,
) -> None:
    """Insert one sub-variable score row for one entity.

    Inputs: the entity, the sub-variable, the core.adapters row and version that produced the
    result, and the validated result. The table's CHECKs repeat ScoreResult's rules, so a row
    can never hold a score without its evidence.
    Implements FR-06 and ADR-004a §2 — nv_signals appends, never updates.
    """
    connection.execute(
        INSERT_SCORE_SQL,
        {
            "entity_id": entity_id,
            "sub_id": sub_id,
            # jsonb because a value is a number or a label (ScoreResult: float | str).
            "value": None if result.value is None else Jsonb(result.value),
            "unit": result.unit,
            "score": result.score,
            "confidence": result.confidence,
            "status": result.status.value,
            "null_reason": result.null_reason,
            "observed_at": result.observed_at,
            "source_ids": list(result.source_ids),
            "adapter_id": adapter_id,
            "adapter_version": adapter_version,
        },
    )
