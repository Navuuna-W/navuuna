# Tests for the input loader: one entity's evidence, as JSON, only the kinds asked for
# (ADR-003). Rows are created as the owner, then read as nv_signals, like the real service.

from datetime import UTC, datetime
from uuid import uuid4

import pytest

from engine.database import DatabaseConnection
from engine.input_kind import InputKind
from engine.input_loader import UnsupportedInputError, load_entity, load_input_rows
from tests.database_helpers import act_as_signal_service
from tests.database_rows import (
    create_consent,
    create_observation,
    create_point_entity,
    create_road_contract,
    create_water_scheme,
)

EARLIER = datetime(2026, 9, 1, tzinfo=UTC)
LATER = datetime(2026, 10, 1, tzinfo=UTC)


def test_the_entity_row_comes_back_without_its_geometry(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)

    entity_row = load_entity(database_connection, entity_id)

    assert entity_row is not None
    assert entity_row["id"] == str(entity_id)
    assert entity_row["external_ref"] == "test:tap:1"
    assert "geom" not in entity_row


def test_a_retired_or_missing_entity_is_not_loaded(database_connection: DatabaseConnection) -> None:
    retired_id = create_point_entity(database_connection, "test:tap:retired", retired_at=LATER)
    act_as_signal_service(database_connection)

    assert load_entity(database_connection, retired_id) is None
    assert load_entity(database_connection, uuid4()) is None


def test_only_the_kinds_asked_for_are_loaded(database_connection: DatabaseConnection) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    create_water_scheme(database_connection, entity_id)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.ENTITY, InputKind.OBSERVATIONS})
    )

    assert loaded_rows == {InputKind.OBSERVATIONS: ()}


def test_observations_come_newest_first(database_connection: DatabaseConnection) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    earlier_id = create_observation(database_connection, entity_id, EARLIER)
    later_id = create_observation(database_connection, entity_id, LATER)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.OBSERVATIONS})
    )

    observation_ids = [row["id"] for row in loaded_rows[InputKind.OBSERVATIONS]]
    assert observation_ids == [str(later_id), str(earlier_id)]


def test_an_observation_whose_consent_was_withdrawn_is_left_out(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    withdrawn_consent_id = create_consent(database_connection, withdrawn_at=LATER)
    active_consent_id = create_consent(database_connection, withdrawn_at=None)
    create_observation(database_connection, entity_id, EARLIER, withdrawn_consent_id)
    kept_with_consent_id = create_observation(
        database_connection, entity_id, EARLIER, active_consent_id
    )
    kept_without_consent_id = create_observation(database_connection, entity_id, LATER)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.OBSERVATIONS})
    )

    observation_ids = {row["id"] for row in loaded_rows[InputKind.OBSERVATIONS]}
    assert observation_ids == {str(kept_with_consent_id), str(kept_without_consent_id)}


def test_records_from_every_records_table_come_back_tagged_with_their_table(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    scheme_id = create_water_scheme(database_connection, entity_id)
    contract_id = create_road_contract(database_connection, entity_id)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(database_connection, entity_id, frozenset({InputKind.RECORDS}))

    tagged_records = {(row["record_table"], row["id"]) for row in loaded_rows[InputKind.RECORDS]}
    assert tagged_records == {
        ("road_contracts", str(contract_id)),
        ("water_schemes", str(scheme_id)),
    }


def test_rows_of_another_entity_never_leak_in(database_connection: DatabaseConnection) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    other_entity_id = create_point_entity(database_connection, "test:tap:2")
    create_observation(database_connection, other_entity_id, EARLIER)
    create_water_scheme(database_connection, other_entity_id)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection,
        entity_id,
        frozenset({InputKind.OBSERVATIONS, InputKind.RECORDS}),
    )

    assert loaded_rows == {InputKind.OBSERVATIONS: (), InputKind.RECORDS: ()}


@pytest.mark.parametrize(
    "kind", [InputKind.EO_STATS, InputKind.NEARBY_ENTITIES, InputKind.NEARBY_WAYS]
)
def test_a_kind_added_in_part_2d_is_reported_as_not_loadable_yet(
    database_connection: DatabaseConnection, kind: InputKind
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)

    with pytest.raises(UnsupportedInputError, match=kind.value):
        load_input_rows(database_connection, entity_id, frozenset({kind}))
