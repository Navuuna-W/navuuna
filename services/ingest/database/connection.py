# Opens the ingest service's connection to PostgreSQL. The login role behind
# NV_INGEST_DATABASE_URL is a member of nv_ingest, the only role allowed to write core.sources,
# core.entities and the raw and records tables (ADR-004a §2). Every loader starts here.

from collections.abc import Mapping
from typing import Any

import psycopg
from psycopg.rows import dict_row

DATABASE_URL_VARIABLE = "NV_INGEST_DATABASE_URL"

# Rows come back as dicts (column name → value).
DatabaseConnection = psycopg.Connection[dict[str, Any]]


class DatabaseSettingsError(Exception):
    """The environment does not say how to reach the database."""


def connect_to_database(environment: Mapping[str, str]) -> DatabaseConnection:
    """Open a connection using NV_INGEST_DATABASE_URL from the given environment.

    Input: the process environment. Output: an open autocommit connection whose rows are dicts.
    Raises DatabaseSettingsError when the variable is missing or empty.
    Implements ADR-004a §2 — the ingest service connects as its own nv_ingest login.
    """
    database_url = environment.get(DATABASE_URL_VARIABLE, "")
    if not database_url:
        raise DatabaseSettingsError(f"set {DATABASE_URL_VARIABLE}, e.g. postgresql://user@host/db")
    # autocommit: each `with connection.transaction()` block then commits as it ends, so a
    # loader that fails half way keeps the blocks it finished.
    return psycopg.connect(database_url, row_factory=dict_row, autocommit=True)
