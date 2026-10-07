# Tests for the recompute consumer (ADR-004a §3): a request rescores one entity, the result is
# announced on batch_written, and the request is acknowledged only after that succeeded.
# Each test uses its own request and batch stream names, deleted afterwards.

import json
from collections.abc import Iterator
from datetime import UTC, datetime
from pathlib import Path
from uuid import UUID, uuid4

import psycopg
import pytest
from redis import Redis
from redis.exceptions import ResponseError

import engine.recompute_consumer
from engine.adapter_sync import sync_adapters
from engine.database import DatabaseConnection
from engine.recompute_consumer import CONSUMER_GROUP, RecomputeConsumer
from engine.registry import discover_adapters
from tests.database_helpers import act_as_signal_service
from tests.database_rows import create_point_entity

FIXTURE_MODULES_ROOT = Path(__file__).parent.parent / "fixtures" / "modules"
# The fake module only; the "other" module needs nearby_ways, which K-09b-2d adds.
FAKE_MODULE_ONLY = {"MODULES_ENABLED": "fake"}
RETIRED_AT = datetime(2026, 10, 1, tzinfo=UTC)


@pytest.fixture
def request_stream(redis_client: Redis) -> Iterator[str]:
    stream_name = f"test.recompute_requested.{uuid4()}"
    yield stream_name
    redis_client.delete(stream_name)


def make_consumer(
    connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    batch_stream: str,
    takeover_idle_ms: int = 60_000,
) -> RecomputeConsumer:
    """A consumer for the fake module, writing as nv_signals, that never waits for messages."""
    act_as_signal_service(connection)
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, FAKE_MODULE_ONLY)
    consumer = RecomputeConsumer(
        connection,
        redis_client,
        adapters,
        sync_adapters(connection, adapters),
        consumer_name="test-reader",
        request_stream=request_stream,
        batch_stream=batch_stream,
        takeover_idle_ms=takeover_idle_ms,
        block_ms=1,
    )
    consumer.ensure_consumer_group()
    return consumer


def request_recompute(redis_client: Redis, stream: str, entity_id: UUID | str) -> None:
    """Publish a request the way Laravel does (ADR-004a §3 fields)."""
    redis_client.xadd(
        stream,
        {
            "entity_id": str(entity_id),
            "reason": "observation_added",
            "requested_by": "system",
            "requested_at": "2026-10-07T10:15:00Z",
        },
    )


def count_pending(redis_client: Redis, stream: str) -> int:
    pending_count: int = redis_client.xpending(stream, CONSUMER_GROUP)["pending"]
    return pending_count


def count_score_rows(connection: DatabaseConnection, entity_id: UUID) -> int:
    found = connection.execute(
        "SELECT count(*) AS row_count FROM scores.sub_variable_scores WHERE entity_id = %s",
        [entity_id],
    ).fetchone()
    assert found is not None
    row_count: int = found["row_count"]
    return row_count


def test_a_request_rescores_the_entity_announces_it_and_is_acknowledged(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1", module="fake")
    consumer = make_consumer(database_connection, redis_client, request_stream, test_stream)
    request_recompute(redis_client, request_stream, entity_id)

    processed_count = consumer.consume_once()

    [(_, announcement)] = redis_client.xrange(test_stream)
    assert processed_count == 1
    assert count_score_rows(database_connection, entity_id) == 2
    assert json.loads(announcement["entity_ids"]) == [str(entity_id)]
    assert announcement["module"] == "fake"
    assert count_pending(redis_client, request_stream) == 0


@pytest.mark.parametrize(
    "entity_case", ["retired", "unknown", "not a uuid", "module without adapters"]
)
def test_a_request_that_can_never_succeed_is_acknowledged_with_nothing_written(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
    entity_case: str,
) -> None:
    retired_id = create_point_entity(
        database_connection, "test:tap:old", retired_at=RETIRED_AT, module="fake"
    )
    other_module_id = create_point_entity(database_connection, "test:pump:1", module="no_adapters")
    requested_ids = {
        "retired": retired_id,
        "unknown": uuid4(),
        "not a uuid": "tap-1",
        "module without adapters": other_module_id,
    }
    consumer = make_consumer(database_connection, redis_client, request_stream, test_stream)
    request_recompute(redis_client, request_stream, requested_ids[entity_case])

    consumer.consume_once()

    assert redis_client.xlen(test_stream) == 0
    assert count_pending(redis_client, request_stream) == 0


def test_a_failed_rescore_leaves_the_request_pending(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1", module="fake")
    consumer = make_consumer(database_connection, redis_client, request_stream, test_stream)
    request_recompute(redis_client, request_stream, entity_id)

    def run_entities_losing_connection(*arguments: object) -> list[UUID]:
        raise psycopg.OperationalError("connection lost")

    monkeypatch.setattr(engine.recompute_consumer, "run_entities", run_entities_losing_connection)

    with pytest.raises(psycopg.OperationalError):
        consumer.consume_once()

    assert count_pending(redis_client, request_stream) == 1


def test_a_request_left_pending_by_another_reader_is_taken_over(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1", module="fake")
    consumer = make_consumer(
        database_connection, redis_client, request_stream, test_stream, takeover_idle_ms=0
    )
    request_recompute(redis_client, request_stream, entity_id)
    # Another reader takes the message and crashes before acknowledging it.
    redis_client.xreadgroup(CONSUMER_GROUP, "crashed-reader", {request_stream: ">"})

    processed_count = consumer.consume_once()

    assert processed_count == 1
    assert count_score_rows(database_connection, entity_id) == 2
    assert count_pending(redis_client, request_stream) == 0


def test_creating_the_group_twice_is_fine(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
) -> None:
    consumer = make_consumer(database_connection, redis_client, request_stream, test_stream)

    consumer.ensure_consumer_group()

    assert redis_client.xinfo_groups(request_stream)[0]["name"] == CONSUMER_GROUP


def test_an_empty_stream_gives_nothing_to_process(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
) -> None:
    consumer = make_consumer(database_connection, redis_client, request_stream, test_stream)

    processed_count = consumer.consume_once()

    assert processed_count == 0


def test_a_redis_error_other_than_an_existing_group_is_raised(
    database_connection: DatabaseConnection,
    redis_client: Redis,
    request_stream: str,
    test_stream: str,
) -> None:
    consumer = make_consumer(database_connection, redis_client, request_stream, test_stream)
    redis_client.delete(request_stream)
    redis_client.set(request_stream, "not a stream")  # makes XGROUP CREATE fail with WRONGTYPE

    with pytest.raises(ResponseError, match="WRONGTYPE"):
        consumer.ensure_consumer_group()
