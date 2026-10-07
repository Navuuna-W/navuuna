# Tests for connect_to_database: the signal service finds its database from the environment.

import os

import pytest

from engine.database import DatabaseSettingsError, connect_to_database
from tests.database_helpers import TEST_DATABASE_URL_VARIABLE


def test_a_missing_database_url_is_reported() -> None:
    environment: dict[str, str] = {}

    with pytest.raises(DatabaseSettingsError, match="NV_SIGNALS_DATABASE_URL"):
        connect_to_database(environment)


def test_rows_come_back_as_dicts() -> None:
    database_url = os.environ.get(TEST_DATABASE_URL_VARIABLE, "")
    if not database_url:
        pytest.skip(f"{TEST_DATABASE_URL_VARIABLE} is not set")
    environment = {"NV_SIGNALS_DATABASE_URL": database_url}

    with connect_to_database(environment) as connection:
        row = connection.execute("SELECT 1 AS answer").fetchone()

    assert row == {"answer": 1}
