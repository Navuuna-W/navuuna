# Tests for the input loader: one entity's evidence, as JSON, only the kinds asked for
# (ADR-003). Rows are created as the owner, then read as nv_signals, like the real service.

from datetime import UTC, datetime
from uuid import uuid4

import pytest

from engine.database import DatabaseConnection
from engine.input_kind import InputKind
from engine.input_loader import MissingRadiusError, load_entity, load_input_rows
from tests.database_helpers import act_as_signal_service
from tests.database_rows import (
    create_consent,
    create_eo_stat,
    create_observation,
    create_point_entity,
    create_road_contract,
    create_road_segment,
    create_ward,
    create_water_scheme,
)

EARLIER = datetime(2026, 9, 1, tzinfo=UTC)
LATER = datetime(2026, 10, 1, tzinfo=UTC)
# The test entity sits at POINT(36.8219 -1.2921). 0.001° of longitude there is about 111 m.
POINT_ABOUT_111_M_EAST = "SRID=4326;POINT(36.8229 -1.2921)"
ROAD_ABOUT_111_M_EAST = "SRID=4326;LINESTRING(36.8229 -1.2921, 36.8229 -1.2911)"
ROAD_ABOUT_1113_M_EAST = "SRID=4326;LINESTRING(36.8319 -1.2921, 36.8319 -1.2911)"
EXPECTED_NEAR_ROAD_DISTANCE_M = 111.3
NEARBY_RADIUS_M = 500


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


def test_eo_stats_come_newest_first(database_connection: DatabaseConnection) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    earlier_id = create_eo_stat(database_connection, entity_id, EARLIER)
    later_id = create_eo_stat(database_connection, entity_id, LATER)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(database_connection, entity_id, frozenset({InputKind.EO_STATS}))

    eo_stat_ids = [row["id"] for row in loaded_rows[InputKind.EO_STATS]]
    assert eo_stat_ids == [str(later_id), str(earlier_id)]


def test_same_area_entities_share_the_ward_and_the_module(
    database_connection: DatabaseConnection,
) -> None:
    ward_id = create_ward(database_connection, "test:ward:1")
    entity_id = create_point_entity(database_connection, "test:tap:1", parent_area_id=ward_id)
    neighbour_id = create_point_entity(database_connection, "test:tap:2", parent_area_id=ward_id)
    create_point_entity(database_connection, "test:tap:3", parent_area_id=ward_id, module="roads")
    create_point_entity(database_connection, "test:tap:4", parent_area_id=ward_id, retired_at=LATER)
    create_point_entity(database_connection, "test:tap:5")  # no ward
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.SAME_AREA_ENTITIES})
    )

    [neighbour_row] = loaded_rows[InputKind.SAME_AREA_ENTITIES]
    assert neighbour_row["id"] == str(neighbour_id)
    assert "geom" not in neighbour_row


def test_an_entity_without_a_ward_has_no_same_area_entities(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    create_point_entity(database_connection, "test:tap:2")
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.SAME_AREA_ENTITIES})
    )

    assert loaded_rows == {InputKind.SAME_AREA_ENTITIES: ()}


def test_nearby_ways_are_road_segments_within_the_radius_nearest_first(
    database_connection: DatabaseConnection,
) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    near_way_id = create_road_segment(database_connection, "osm:w1", ROAD_ABOUT_111_M_EAST)
    create_road_segment(database_connection, "osm:w2", ROAD_ABOUT_1113_M_EAST)
    create_point_entity(database_connection, "test:tap:2")  # a point, not a way
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.NEARBY_WAYS}), NEARBY_RADIUS_M
    )

    [way_row] = loaded_rows[InputKind.NEARBY_WAYS]
    assert way_row["id"] == str(near_way_id)
    assert way_row["metadata"] == {"osm": {"surface": "gravel"}}
    assert way_row["distance_m"] == pytest.approx(EXPECTED_NEAR_ROAD_DISTANCE_M, abs=1)
    assert "geom" not in way_row


def test_nearby_entities_leave_out_areas_retired_entities_and_the_entity_itself(
    database_connection: DatabaseConnection,
) -> None:
    ward_id = create_ward(database_connection, "test:ward:1")
    entity_id = create_point_entity(database_connection, "test:tap:1", parent_area_id=ward_id)
    neighbour_id = create_point_entity(
        database_connection, "test:tap:2", geom=POINT_ABOUT_111_M_EAST
    )
    way_id = create_road_segment(database_connection, "osm:w1", ROAD_ABOUT_111_M_EAST)
    create_point_entity(database_connection, "test:tap:old", retired_at=LATER)
    act_as_signal_service(database_connection)

    loaded_rows = load_input_rows(
        database_connection, entity_id, frozenset({InputKind.NEARBY_ENTITIES}), NEARBY_RADIUS_M
    )

    nearby_ids = {row["id"] for row in loaded_rows[InputKind.NEARBY_ENTITIES]}
    assert nearby_ids == {str(neighbour_id), str(way_id)}


def test_nearby_rows_without_a_radius_are_refused(database_connection: DatabaseConnection) -> None:
    entity_id = create_point_entity(database_connection, "test:tap:1")
    act_as_signal_service(database_connection)

    with pytest.raises(MissingRadiusError):
        load_input_rows(database_connection, entity_id, frozenset({InputKind.NEARBY_WAYS}))
