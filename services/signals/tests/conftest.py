# Shared pytest fixtures. Database tests run against a PostgreSQL that has the Laravel
# migrations applied (CI builds one; locally see services/signals/README.md). Each test runs
# in one transaction that is rolled back, so tests never leave rows behind.

import os
from collections.abc import Iterator

import psycopg
import pytest
from psycopg.rows import dict_row

from engine.database import DatabaseConnection
from tests.database_helpers import TEST_DATABASE_URL_VARIABLE


@pytest.fixture
def database_connection() -> Iterator[DatabaseConnection]:
    """A connection as the database owner, inside a transaction rolled back after the test.

    Skips the test when NV_TEST_DATABASE_URL is not set, so the pure tests run anywhere.
    CI always sets it, and the 100 % coverage gate fails if database tests were skipped.
    """
    database_url = os.environ.get(TEST_DATABASE_URL_VARIABLE, "")
    if not database_url:
        pytest.skip(f"{TEST_DATABASE_URL_VARIABLE} is not set")
    with psycopg.connect(database_url, row_factory=dict_row) as connection:
        connection.execute("SELECT 1")  # opens the transaction the test runs in
        yield connection
        connection.rollback()
