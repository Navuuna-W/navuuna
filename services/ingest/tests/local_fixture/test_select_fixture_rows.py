# Checks which entities the local fixture picks from a ward, and that operator names are
# left behind (work pack D-14).

from database.connection import DatabaseConnection
from local_fixture.select_fixture_rows import select_nearest_in_ward

WARD_EXTERNAL_REF = "test:fixture:ward"


def test_entities_nearest_the_middle_of_the_ward_are_picked_first(
    connection_with_ward: DatabaseConnection,
) -> None:
    water_points = select_nearest_in_ward(connection_with_ward, WARD_EXTERNAL_REF, "point", 2)

    assert [entity.external_ref for entity in water_points] == [
        "test:fixture:middle",
        "test:fixture:near",
    ]


def test_operator_names_never_reach_the_fixture(
    connection_with_ward: DatabaseConnection,
) -> None:
    water_points = select_nearest_in_ward(connection_with_ward, WARD_EXTERNAL_REF, "point", 3)

    assert all("operator" not in entity.metadata for entity in water_points)
