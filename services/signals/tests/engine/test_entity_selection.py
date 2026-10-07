# Tests for find_entity_ids: which entities a module run picks, page by page (K-09, E15).

from datetime import UTC, datetime

from engine.database import DatabaseConnection
from engine.entity_selection import find_entity_ids
from engine.entity_type import EntityType
from tests.database_helpers import act_as_signal_service
from tests.database_rows import create_point_entity

TEST_MODULE = "selection_test"
POINTS_ONLY = frozenset({EntityType.POINT})
PAGE_SIZE = 10
RETIRED_AT = datetime(2026, 10, 1, tzinfo=UTC)


def test_only_active_entities_of_the_module_and_its_types_are_picked(
    database_connection: DatabaseConnection,
) -> None:
    active_id = create_point_entity(database_connection, "test:1", module=TEST_MODULE)
    create_point_entity(database_connection, "test:2", module="other_module")
    create_point_entity(database_connection, "test:3", retired_at=RETIRED_AT, module=TEST_MODULE)
    act_as_signal_service(database_connection)

    picked_ids = find_entity_ids(database_connection, TEST_MODULE, POINTS_ONLY, None, PAGE_SIZE)

    assert picked_ids == [active_id]


def test_entities_of_a_type_no_adapter_scores_are_not_picked(
    database_connection: DatabaseConnection,
) -> None:
    create_point_entity(database_connection, "test:1", module=TEST_MODULE)
    act_as_signal_service(database_connection)
    areas_only = frozenset({EntityType.AREA})

    picked_ids = find_entity_ids(database_connection, TEST_MODULE, areas_only, None, PAGE_SIZE)

    assert picked_ids == []


def test_pages_follow_on_from_the_last_id_without_gaps_or_repeats(
    database_connection: DatabaseConnection,
) -> None:
    created_ids = [
        create_point_entity(database_connection, f"test:{number}", module=TEST_MODULE)
        for number in range(5)
    ]
    act_as_signal_service(database_connection)

    first_page = find_entity_ids(database_connection, TEST_MODULE, POINTS_ONLY, None, 2)
    second_page = find_entity_ids(database_connection, TEST_MODULE, POINTS_ONLY, first_page[-1], 2)
    third_page = find_entity_ids(database_connection, TEST_MODULE, POINTS_ONLY, second_page[-1], 2)

    assert first_page + second_page + third_page == sorted(created_ids)
    assert len(third_page) == 1
