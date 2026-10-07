# Tests for run_module: a module is scored chunk by chunk and each chunk is announced on
# signals.batch_written (K-09 work pack, ADR-004a §3). Uses the fake module's adapters.

import json
from datetime import UTC, datetime
from pathlib import Path

from redis import Redis

from engine.adapter_inputs import AdapterInputs
from engine.adapter_sync import sync_adapters
from engine.database import DatabaseConnection
from engine.module_run import run_module
from engine.registered_adapter import RegisteredAdapter
from engine.registry import discover_adapters
from engine.score_result import ScoreResult
from tests.database_helpers import act_as_signal_service
from tests.database_rows import create_point_entity

FIXTURE_MODULES_ROOT = Path(__file__).parent.parent / "fixtures" / "modules"
# The fake module only, so the expected rows and versions stay short.
FAKE_MODULE_ONLY = {"MODULES_ENABLED": "fake"}
AS_OF = datetime(2026, 10, 7, 12, 0, tzinfo=UTC)


def fake_adapters() -> list[RegisteredAdapter]:
    return discover_adapters(FIXTURE_MODULES_ROOT, FAKE_MODULE_ONLY)


def test_each_chunk_is_announced_with_the_same_batch_id(
    database_connection: DatabaseConnection, redis_client: Redis, test_stream: str
) -> None:
    entity_ids = [
        create_point_entity(database_connection, f"test:tap:{number}", module="fake")
        for number in range(5)
    ]
    act_as_signal_service(database_connection)
    adapters = fake_adapters()
    adapter_ids = sync_adapters(database_connection, adapters)

    summary = run_module(
        database_connection,
        redis_client,
        "fake",
        adapters,
        adapter_ids,
        AS_OF,
        chunk_size=2,
        stream=test_stream,
    )

    messages = [message for _, message in redis_client.xrange(test_stream)]
    announced_ids: list[str] = []
    for message in messages:
        announced_ids.extend(json.loads(message["entity_ids"]))
    assert len(messages) == 3
    assert {message["batch_id"] for message in messages} == {str(summary.batch_id)}
    assert announced_ids == [str(entity_id) for entity_id in sorted(entity_ids)]
    assert summary.scored_entity_count == 5


def test_the_message_lists_the_module_and_its_adapter_versions(
    database_connection: DatabaseConnection, redis_client: Redis, test_stream: str
) -> None:
    create_point_entity(database_connection, "test:tap:1", module="fake")
    act_as_signal_service(database_connection)
    adapters = fake_adapters()
    adapter_ids = sync_adapters(database_connection, adapters)

    run_module(
        database_connection, redis_client, "fake", adapters, adapter_ids, AS_OF, stream=test_stream
    )

    [(_, message)] = redis_client.xrange(test_stream)
    assert message["module"] == "fake"
    assert json.loads(message["adapter_versions"]) == {"1.1": "1.0.0", "2.1": "1.0.0"}


def test_a_chunk_with_no_new_rows_is_not_announced(
    database_connection: DatabaseConnection, redis_client: Redis, test_stream: str
) -> None:
    create_point_entity(database_connection, "test:tap:1", module="fake")
    act_as_signal_service(database_connection)

    def score_by_failing(inputs: AdapterInputs) -> ScoreResult:
        raise RuntimeError("adapter bug")

    failing_adapters = [
        RegisteredAdapter(spec=adapter.spec, score=score_by_failing, code_ref=adapter.code_ref)
        for adapter in fake_adapters()
    ]
    adapter_ids = sync_adapters(database_connection, failing_adapters)

    summary = run_module(
        database_connection,
        redis_client,
        "fake",
        failing_adapters,
        adapter_ids,
        AS_OF,
        stream=test_stream,
    )

    assert summary.scored_entity_count == 0
    assert redis_client.xlen(test_stream) == 0
