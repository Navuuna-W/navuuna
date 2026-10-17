# Checks the SQL text of the local fixture: that awkward names cannot break it, and that it
# says where its data comes from.

from local_fixture.fixture_rows import FixtureEntity, FixtureSource
from local_fixture.write_fixture_sql import quote_sql_text, write_fixture_sql

TEST_SOURCE = FixtureSource(
    name="A test source",
    kind="vector",
    base_url=None,
    licence="Test licence",
    attribution="Test credit",
)
TEST_WARD = FixtureEntity(
    id="00000000-0000-7000-8000-000000000001",
    entity_type="area",
    module=None,
    name="Sarang'ombe",
    external_ref="test:fixture:ward",
    geometry_wkt="POLYGON((36.85 -1.27,36.86 -1.27,36.86 -1.28,36.85 -1.27))",
    radius_m=None,
    metadata={"area_kind": "ward"},
    parent_external_ref=None,
    source_name="A test source",
)


def test_single_quote_in_a_name_is_doubled_so_it_cannot_end_the_text_early() -> None:
    quoted_name = quote_sql_text("Sarang'ombe")

    assert quoted_name == "'Sarang''ombe'"


def test_missing_value_is_written_as_null_not_as_empty_text() -> None:
    quoted_value = quote_sql_text(None)

    assert quoted_value == "NULL"


def test_fixture_sql_starts_with_the_data_credits() -> None:
    fixture_sql = write_fixture_sql([TEST_SOURCE], [[TEST_WARD]])

    assert "OpenStreetMap contributors" in fixture_sql
    assert "not an official" in fixture_sql


def test_fixture_sql_skips_rows_that_are_already_loaded() -> None:
    fixture_sql = write_fixture_sql([TEST_SOURCE], [[TEST_WARD]])

    assert "ON CONFLICT (name) DO NOTHING" in fixture_sql
    assert "ON CONFLICT (external_ref) DO NOTHING" in fixture_sql
