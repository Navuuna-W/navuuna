# Checks that the source catalogue reaches core.sources as the nv_ingest role, and that
# running the command again updates rows instead of adding more (work pack D-06).

import pytest

from database.connection import DatabaseConnection
from sources.register_sources import main, register_sources
from sources.source_catalogue import SOURCE_CATALOGUE
from sources.source_definition import SourceDefinition
from sources.source_kind import SourceKind

COUNT_CATALOGUE_ROWS_SQL = "SELECT count(*) AS row_count FROM core.sources WHERE name = ANY(%s)"
SELECT_SOURCE_SQL = "SELECT * FROM core.sources WHERE name = %s"

TEST_SOURCE = SourceDefinition(
    name="A test source that is not in the catalogue",
    kind=SourceKind.VECTOR,
    base_url="https://example.org/",
    licence="First licence text",
    attribution="First attribution text",
)


def count_catalogue_rows(connection: DatabaseConnection) -> int:
    """How many core.sources rows carry a name from the catalogue."""
    catalogue_names = [source.name for source in SOURCE_CATALOGUE]
    row = connection.execute(COUNT_CATALOGUE_ROWS_SQL, [catalogue_names]).fetchone()
    assert row is not None
    row_count: int = row["row_count"]
    return row_count


def test_registering_the_catalogue_stores_one_row_per_source(
    ingest_connection: DatabaseConnection,
) -> None:
    written_count = register_sources(ingest_connection, SOURCE_CATALOGUE)

    assert written_count == len(SOURCE_CATALOGUE)
    assert count_catalogue_rows(ingest_connection) == len(SOURCE_CATALOGUE)


def test_registering_twice_leaves_one_row_per_source(
    ingest_connection: DatabaseConnection,
) -> None:
    register_sources(ingest_connection, SOURCE_CATALOGUE)

    register_sources(ingest_connection, SOURCE_CATALOGUE)

    assert count_catalogue_rows(ingest_connection) == len(SOURCE_CATALOGUE)


def test_changed_attribution_text_updates_the_existing_row(
    ingest_connection: DatabaseConnection,
) -> None:
    register_sources(ingest_connection, [TEST_SOURCE])
    changed_source = TEST_SOURCE.model_copy(update={"attribution": "Second attribution text"})

    register_sources(ingest_connection, [changed_source])

    rows = ingest_connection.execute(SELECT_SOURCE_SQL, [TEST_SOURCE.name]).fetchall()
    assert len(rows) == 1
    assert rows[0]["attribution"] == "Second attribution text"
    assert rows[0]["licence"] == "First licence text"


def test_inactive_source_is_stored_as_not_active(ingest_connection: DatabaseConnection) -> None:
    inactive_source = TEST_SOURCE.model_copy(update={"is_active": False})

    register_sources(ingest_connection, [inactive_source])

    row = ingest_connection.execute(SELECT_SOURCE_SQL, [TEST_SOURCE.name]).fetchone()
    assert row is not None
    assert row["active"] is False


def test_command_without_a_database_url_exits_with_an_error(
    monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]
) -> None:
    monkeypatch.delenv("NV_INGEST_DATABASE_URL", raising=False)

    exit_code = main()

    assert exit_code == 1
    assert "NV_INGEST_DATABASE_URL" in capsys.readouterr().err
