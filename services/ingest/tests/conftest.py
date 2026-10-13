# Shared pytest fixtures. Database tests run against a PostgreSQL that has the Laravel
# migrations applied (see services/ingest/README.md). Each test runs in one transaction that
# is rolled back, so tests never leave rows behind.

import os
from collections.abc import Iterator

import psycopg
import pytest
from psycopg.rows import dict_row

from database.connection import DatabaseConnection

# Points at a PostgreSQL with the Laravel migrations applied; unset means skip database tests.
TEST_DATABASE_URL_VARIABLE = "NV_TEST_DATABASE_URL"
INGEST_SERVICE_ROLE = "nv_ingest"


@pytest.fixture
def ingest_connection() -> Iterator[DatabaseConnection]:
    """A connection acting as nv_ingest, inside a transaction rolled back after the test.

    Acting as nv_ingest means the table GRANTs are what decide whether a write works
    (ADR-004a §2). Skips the test when NV_TEST_DATABASE_URL is not set.
    """
    database_url = os.environ.get(TEST_DATABASE_URL_VARIABLE, "")
    if not database_url:
        pytest.skip(f"{TEST_DATABASE_URL_VARIABLE} is not set")
    with psycopg.connect(database_url, row_factory=dict_row) as connection:
        connection.execute(f"SET LOCAL ROLE {INGEST_SERVICE_ROLE}")
        yield connection
        connection.rollback()
