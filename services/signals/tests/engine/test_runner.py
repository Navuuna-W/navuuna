# Tests for the runner: adapters run per entity, in dependency order, and valid results are
# appended to scores.sub_variable_scores as nv_signals (FR-06, ADR-003, ADR-004a).
# The fake module covers the normal flow; small adapters defined here cover the edge cases.

from datetime import UTC, datetime
from pathlib import Path
from typing import Any
from uuid import UUID

import psycopg
import pytest

import engine.runner
from engine.adapter_inputs import AdapterInputs, InputRows
from engine.adapter_spec import AdapterSpec
from engine.adapter_sync import sync_adapters
from engine.database import DatabaseConnection
from engine.input_kind import InputKind
from engine.registered_adapter import RegisteredAdapter, ScoreFunction
from engine.registry import discover_adapters
from engine.runner import run_entities, run_entity
from engine.score_result import ScoreResult, ScoreStatus
from tests.database_helpers import act_as_signal_service
from tests.database_rows import create_observation, create_point_entity

FIXTURE_MODULES_ROOT = Path(__file__).parent.parent / "fixtures" / "modules"
# The fake module only; the "other" module needs nearby_ways, which K-09b-2d adds.
FAKE_MODULE_ONLY = {"MODULES_ENABLED": "fake"}
AS_OF = datetime(2026, 10, 7, 12, 0, tzinfo=UTC)
OBSERVED_AT = datetime(2026, 10, 1, tzinfo=UTC)
NOT_MEASURED = ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="Test reason")


def make_adapter(
    sub_id: str,
    score_function: ScoreFunction,
    entity_types: list[str] | None = None,
    depends_on_sub_ids: list[str] | None = None,
) -> RegisteredAdapter:
    spec = AdapterSpec(
        module="test",
        sub_id=sub_id,
        entity_types=entity_types or ["point"],
        version="1.0.0",
        requires=["entity"],
        depends_on_sub_ids=depends_on_sub_ids or [],
        signal_description="Test adapter.",
    )
    return RegisteredAdapter(spec=spec, score=score_function, code_ref=f"test/{sub_id}.py")


def score_not_measured(inputs: AdapterInputs) -> ScoreResult:
    return NOT_MEASURED


def read_score_rows(connection: DatabaseConnection, entity_id: UUID) -> list[dict[str, Any]]:
    return connection.execute(
        "SELECT * FROM scores.sub_variable_scores WHERE entity_id = %s ORDER BY sub_id",
        [entity_id],
    ).fetchall()


def test_each_fitting_adapter_writes_one_row(database_connection: DatabaseConnection) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    observation_id = create_observation(database_connection, entity_id, OBSERVED_AT)
    act_as_signal_service(database_connection)
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, FAKE_MODULE_ONLY)
    adapter_ids = sync_adapters(database_connection, adapters)

    is_scored = run_entity(database_connection, adapters, adapter_ids, entity_id, AS_OF)

    presence_row, existence_gap_row = read_score_rows(database_connection, entity_id)
    assert is_scored
    assert presence_row["sub_id"] == "1.1"
    assert presence_row["status"] == "measured"
    assert presence_row["value"] == "present"
    assert presence_row["score"] == 100.0
    assert presence_row["confidence"] == 0.8
    assert presence_row["observed_at"] == AS_OF
    assert presence_row["source_ids"] == [observation_id]
    assert presence_row["adapter_id"] == adapter_ids["1.1"]
    assert presence_row["adapter_version"] == "1.0.0"
    assert existence_gap_row["sub_id"] == "2.1"


def test_an_unmeasured_result_keeps_its_reason_and_no_number(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)
    adapters = discover_adapters(FIXTURE_MODULES_ROOT, FAKE_MODULE_ONLY)
    adapter_ids = sync_adapters(database_connection, adapters)

    run_entity(database_connection, adapters, adapter_ids, entity_id, AS_OF)

    presence_row = read_score_rows(database_connection, entity_id)[0]
    assert presence_row["status"] == "null_not_measured"
    assert presence_row["null_reason"] == "No observations"
    assert presence_row["value"] is None
    assert presence_row["score"] is None
    assert presence_row["confidence"] is None


def test_an_adapter_receives_the_result_it_depends_on(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)
    received_results: dict[str, ScoreResult] = {}

    def score_existence_gap(inputs: AdapterInputs) -> ScoreResult:
        received_results.update(inputs.sub_variable_results)
        return NOT_MEASURED

    adapters = [
        make_adapter("1.1", score_not_measured),
        make_adapter("2.1", score_existence_gap, depends_on_sub_ids=["1.1"]),
    ]
    adapter_ids = sync_adapters(database_connection, adapters)

    run_entity(database_connection, adapters, adapter_ids, entity_id, AS_OF)

    assert received_results == {"1.1": NOT_MEASURED}


def test_an_adapter_for_another_entity_type_is_not_called(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)
    adapters = [make_adapter("5.2", score_not_measured, entity_types=["segment"])]
    adapter_ids = sync_adapters(database_connection, adapters)

    is_scored = run_entity(database_connection, adapters, adapter_ids, entity_id, AS_OF)

    assert not is_scored
    assert read_score_rows(database_connection, entity_id) == []


def test_a_retired_entity_is_skipped(database_connection: DatabaseConnection) -> None:
    retired_id = create_point_entity(database_connection, "test:tap:old", retired_at=AS_OF)
    active_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)
    adapters = [make_adapter("1.1", score_not_measured)]
    adapter_ids = sync_adapters(database_connection, adapters)

    scored_ids = run_entities(
        database_connection, adapters, adapter_ids, [retired_id, active_id], AS_OF
    )

    assert scored_ids == [active_id]
    assert read_score_rows(database_connection, retired_id) == []


def score_by_raising(inputs: AdapterInputs) -> ScoreResult:
    # Building an invalid ScoreResult raises a ValidationError, like a buggy adapter would.
    return ScoreResult(status=ScoreStatus.MEASURED)


def score_with_wrong_type(inputs: AdapterInputs) -> ScoreResult:
    wrong_result: Any = {"score": 50}
    return wrong_result  # type: ignore[no-any-return]


@pytest.mark.parametrize("broken_score_function", [score_by_raising, score_with_wrong_type])
def test_a_broken_adapter_writes_nothing_and_the_others_still_run(
    database_connection: DatabaseConnection,
    broken_score_function: ScoreFunction,
    caplog: pytest.LogCaptureFixture,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)
    adapters = [
        make_adapter("1.1", broken_score_function),
        make_adapter("1.2", score_not_measured),
    ]
    adapter_ids = sync_adapters(database_connection, adapters)

    run_entity(database_connection, adapters, adapter_ids, entity_id, AS_OF)

    assert [row["sub_id"] for row in read_score_rows(database_connection, entity_id)] == ["1.2"]
    assert "test/1.1.py" in caplog.text


def test_an_error_on_a_later_entity_rolls_back_the_whole_chunk(
    database_connection: DatabaseConnection, monkeypatch: pytest.MonkeyPatch
) -> None:
    first_id = create_point_entity(database_connection, "test:tap:1")
    second_id = create_point_entity(database_connection, "test:tap:2")
    act_as_signal_service(database_connection)
    adapters = [make_adapter("1.1", score_not_measured)]
    adapter_ids = sync_adapters(database_connection, adapters)
    real_load_input_rows = engine.runner.load_input_rows

    def load_input_rows_failing_on_second(
        connection: DatabaseConnection, entity_id: UUID, kinds: frozenset[InputKind]
    ) -> dict[InputKind, InputRows]:
        # Stands in for a lost connection or a bad query after the first entity was written.
        if entity_id == second_id:
            raise psycopg.OperationalError("connection lost")
        return real_load_input_rows(connection, entity_id, kinds)

    monkeypatch.setattr(engine.runner, "load_input_rows", load_input_rows_failing_on_second)

    with pytest.raises(psycopg.OperationalError):
        run_entities(database_connection, adapters, adapter_ids, [first_id, second_id], AS_OF)

    assert read_score_rows(database_connection, first_id) == []
