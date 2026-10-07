# Tells the Laravel rollup which entities have new sub-variable scores (ADR-004a §3).
# module_run.py calls this after each chunk commits; the rollup worker on Box A reads the
# stream as consumer group "rollup" and rolls those entities up.

import json
from datetime import UTC, datetime
from uuid import UUID

from redis import Redis
from redis.typing import EncodableT, FieldT

BATCH_WRITTEN_STREAM = "signals.batch_written"
# ADR-004a §3: a message lists at most 500 entities; bigger runs send several messages.
MAX_ENTITY_IDS_PER_MESSAGE = 500
# ADR-004a §3: XADD … MAXLEN ~ 100000 keeps the stream from growing for ever.
STREAM_MAX_LENGTH = 100_000


class BatchTooLargeError(Exception):
    """More entity ids than one batch_written message may carry."""


def publish_batch_written(
    redis_client: Redis,
    batch_id: UUID,
    entity_ids: list[UUID],
    module: str,
    adapter_versions: dict[str, str],
    written_at: datetime,
    stream: str = BATCH_WRITTEN_STREAM,
) -> None:
    """Add one signals.batch_written message, with the fields exactly as ADR-004a §3 lists.

    Inputs: the run's batch id, up to 500 entity ids, the module, the version of each
    adapter that ran (sub_id → version) and when the chunk was committed. Stream fields are
    flat strings, so lists and maps go as JSON and the time as ISO 8601 UTC.
    `stream` is only changed by tests, so they never touch the real stream.
    Implements ADR-004a §3 — the signals.batch_written contract.
    """
    if len(entity_ids) > MAX_ENTITY_IDS_PER_MESSAGE:
        raise BatchTooLargeError(
            f"{len(entity_ids)} entity ids; one message carries at most "
            f"{MAX_ENTITY_IDS_PER_MESSAGE}"
        )
    message: dict[FieldT, EncodableT] = {
        "batch_id": str(batch_id),
        "entity_ids": json.dumps([str(entity_id) for entity_id in entity_ids]),
        "module": module,
        "adapter_versions": json.dumps(adapter_versions, sort_keys=True),
        "written_at": format_utc_timestamp(written_at),
    }
    redis_client.xadd(stream, message, maxlen=STREAM_MAX_LENGTH, approximate=True)


def format_utc_timestamp(moment: datetime) -> str:
    """Return the moment as ISO 8601 UTC with a Z, e.g. 2026-09-29T10:15:00Z (ADR-004a §3)."""
    return moment.astimezone(UTC).strftime("%Y-%m-%dT%H:%M:%SZ")
