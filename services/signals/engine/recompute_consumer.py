# Rescores single entities when Laravel asks for it on signals.recompute_requested (ADR-004a §3).
# Laravel publishes a request when an entity's evidence changes (a new or withdrawn observation,
# an admin action). This reader rescores the entity, commits, tells the rollup on
# signals.batch_written, and only then acknowledges the request.

import logging
from collections.abc import Mapping
from dataclasses import dataclass
from datetime import UTC, datetime
from typing import Any, cast
from uuid import UUID

from redis import Redis
from redis.exceptions import ResponseError

from engine.batch_publisher import BATCH_WRITTEN_STREAM, publish_batch_written
from engine.database import DatabaseConnection
from engine.input_loader import load_entity
from engine.module_run import collect_adapter_versions, create_batch_id
from engine.registered_adapter import RegisteredAdapter
from engine.runner import run_entities

RECOMPUTE_STREAM = "signals.recompute_requested"
CONSUMER_GROUP = "runner"
# ADR-004a §3: a message pending for more than 5 minutes is taken over by another reader.
PENDING_TAKEOVER_MS = 5 * 60 * 1000
# How long one read waits for new messages before it returns with none.
READ_BLOCK_MS = 5_000
READ_BATCH_SIZE = 10
# Start a new group at the first message: recompute is idempotent, so replaying is safe.
GROUP_START_ID = "0"

logger = logging.getLogger(__name__)

StreamMessage = tuple[str, Mapping[str, str]]
# redis-py types its sync and async clients together, so every reply is "Awaitable | Any".
# Our client is the sync one, so a reply is a plain list.
SyncReply = list[Any]


@dataclass
class RecomputeConsumer:
    """One reader in the "runner" consumer group, with everything it needs to rescore.

    adapters and adapter_ids come from discover_adapters and sync_adapters. consumer_name
    must be unique per running reader. The stream names and timings are only changed by tests.
    Implements ADR-004a §3 — signals.recompute_requested, read by group "runner".
    """

    connection: DatabaseConnection
    redis_client: Redis
    adapters: list[RegisteredAdapter]
    adapter_ids: dict[str, UUID]
    consumer_name: str
    request_stream: str = RECOMPUTE_STREAM
    batch_stream: str = BATCH_WRITTEN_STREAM
    takeover_idle_ms: int = PENDING_TAKEOVER_MS
    block_ms: int = READ_BLOCK_MS

    def ensure_consumer_group(self) -> None:
        """Create the "runner" group (and the stream) unless it already exists."""
        try:
            self.redis_client.xgroup_create(
                self.request_stream, CONSUMER_GROUP, id=GROUP_START_ID, mkstream=True
            )
        except ResponseError as error:
            if "BUSYGROUP" not in str(error):
                raise

    def consume_once(self) -> int:
        """Take over stale messages, or else read new ones; process them; return how many."""
        messages = self.claim_stale_messages()
        if not messages:
            messages = self.read_new_messages()
        for message_id, fields in messages:
            self.process_request(message_id, fields)
        return len(messages)

    def claim_stale_messages(self) -> list[StreamMessage]:
        """Take over messages another reader left pending too long (XAUTOCLAIM)."""
        response = cast(
            SyncReply,
            self.redis_client.xautoclaim(
                self.request_stream,
                CONSUMER_GROUP,
                self.consumer_name,
                min_idle_time=self.takeover_idle_ms,
                count=READ_BATCH_SIZE,
            ),
        )
        # Reply: [next start id, claimed messages, ids of deleted messages].
        claimed_messages: list[StreamMessage] = response[1]
        return claimed_messages

    def read_new_messages(self) -> list[StreamMessage]:
        """Read messages no reader has seen yet (XREADGROUP >), waiting up to block_ms."""
        response = cast(
            SyncReply,
            self.redis_client.xreadgroup(
                CONSUMER_GROUP,
                self.consumer_name,
                {self.request_stream: ">"},
                count=READ_BATCH_SIZE,
                block=self.block_ms,
            ),
        )
        if not response:
            return []
        # Reply: [[stream name, messages]] — one entry, because we read one stream.
        new_messages: list[StreamMessage] = response[0][1]
        return new_messages

    def process_request(self, message_id: str, fields: Mapping[str, str]) -> None:
        """Rescore the requested entity, announce it, then acknowledge the message.

        An invalid id or a retired/unknown entity is logged and acknowledged with nothing
        written — retrying would never succeed. If rescoring raises, the message is left
        pending so it is processed again (ADR-004a §3: XACK only after commit).
        """
        entity_id = parse_entity_id(fields.get("entity_id", ""))
        if entity_id is None:
            logger.warning(
                "recompute %s: invalid entity_id %r", message_id, fields.get("entity_id")
            )
        else:
            self.rescore_entity(entity_id)
        self.redis_client.xack(self.request_stream, CONSUMER_GROUP, message_id)

    def rescore_entity(self, entity_id: UUID) -> None:
        """Run the entity's module adapters in one committed chunk and announce new rows."""
        entity = load_entity(self.connection, entity_id)
        if entity is None:
            logger.info("recompute: entity %s is retired or unknown; nothing to do", entity_id)
            return
        # Only the entity's own module, as in run_module: a water point is never scored by
        # another module's adapters just because the entity types match.
        module_adapters = [
            adapter for adapter in self.adapters if adapter.spec.module == entity["module"]
        ]
        as_of = datetime.now(UTC)
        scored_ids = run_entities(
            self.connection, module_adapters, self.adapter_ids, [entity_id], as_of
        )
        if not scored_ids:
            return
        publish_batch_written(
            self.redis_client,
            create_batch_id(self.connection),
            scored_ids,
            module_adapters[0].spec.module,
            collect_adapter_versions(module_adapters),
            datetime.now(UTC),
            self.batch_stream,
        )


def parse_entity_id(raw_entity_id: str) -> UUID | None:
    """Return the UUID in a request's entity_id field, or None if it is not a UUID."""
    try:
        return UUID(raw_entity_id)
    except ValueError:
        return None
