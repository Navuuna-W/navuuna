# Tests for sync_adapters: core.adapters mirrors what the registry found (ADR-003, ADR-004a).
# Every test writes as nv_signals, so the GRANTs in the core.adapters migration are tested too.

from pathlib import Path
from uuid import UUID

from engine.adapter_sync import sync_adapters
from engine.database import DatabaseConnection
from engine.registered_adapter import RegisteredAdapter
from engine.registry import discover_adapters
from tests.database_helpers import act_as_signal_service

FIXTURE_MODULES_ROOT = Path(__file__).parent.parent / "fixtures" / "modules"
ALL_MODULES_ENABLED: dict[str, str] = {}


def fixture_adapters(environment: dict[str, str] | None = None) -> list[RegisteredAdapter]:
    return discover_adapters(FIXTURE_MODULES_ROOT, environment or ALL_MODULES_ENABLED)


def with_version(adapter: RegisteredAdapter, version: str) -> RegisteredAdapter:
    new_spec = adapter.spec.model_copy(update={"version": version})
    return RegisteredAdapter(spec=new_spec, score=adapter.score, code_ref=adapter.code_ref)


def read_adapter_row(connection: DatabaseConnection, adapter_id: UUID) -> dict[str, object]:
    row = connection.execute("SELECT * FROM core.adapters WHERE id = %s", [adapter_id]).fetchone()
    assert row is not None
    return row


def test_a_new_adapter_gets_a_row(database_connection: DatabaseConnection) -> None:
    act_as_signal_service(database_connection)

    adapter_ids = sync_adapters(database_connection, fixture_adapters())

    row = read_adapter_row(database_connection, adapter_ids["5.2"])
    assert row["module"] == "other"
    assert row["version"] == "0.1.0"
    assert row["enabled"] is True
    assert row["code_ref"] == "modules/other/adapters/connection_quality.py"
    assert row["entity_types"] == ["point", "segment"]
    assert row["signal_description"] == "Fake connection quality: never measured."


def test_syncing_twice_keeps_the_same_rows(database_connection: DatabaseConnection) -> None:
    act_as_signal_service(database_connection)
    first_ids = sync_adapters(database_connection, fixture_adapters())

    second_ids = sync_adapters(database_connection, fixture_adapters())

    assert second_ids == first_ids


def test_a_new_version_gets_its_own_row_and_the_old_one_is_switched_off(
    database_connection: DatabaseConnection,
) -> None:
    act_as_signal_service(database_connection)
    old_ids = sync_adapters(database_connection, fixture_adapters())
    newer_adapters = [with_version(adapter, "2.0.0") for adapter in fixture_adapters()]

    new_ids = sync_adapters(database_connection, newer_adapters)

    assert new_ids["1.1"] != old_ids["1.1"]
    assert read_adapter_row(database_connection, new_ids["1.1"])["enabled"] is True
    assert read_adapter_row(database_connection, old_ids["1.1"])["enabled"] is False


def test_a_switched_off_module_has_its_rows_switched_off(
    database_connection: DatabaseConnection,
) -> None:
    act_as_signal_service(database_connection)
    all_ids = sync_adapters(database_connection, fixture_adapters())

    sync_adapters(database_connection, fixture_adapters({"MODULES_ENABLED": "fake"}))

    assert read_adapter_row(database_connection, all_ids["5.2"])["enabled"] is False
    assert read_adapter_row(database_connection, all_ids["1.1"])["enabled"] is True


def test_a_switched_off_row_is_switched_back_on_when_its_module_returns(
    database_connection: DatabaseConnection,
) -> None:
    act_as_signal_service(database_connection)
    sync_adapters(database_connection, fixture_adapters({"MODULES_ENABLED": "fake"}))

    adapter_ids = sync_adapters(database_connection, fixture_adapters())

    assert read_adapter_row(database_connection, adapter_ids["5.2"])["enabled"] is True
