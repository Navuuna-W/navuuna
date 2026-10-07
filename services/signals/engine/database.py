# Opens the signal service's connection to PostgreSQL (Bible §8.2: Box B talks to the DB on Box A).
# The login role behind NV_SIGNALS_DATABASE_URL is a member of nv_signals, so it can only write
# core.adapters and scores.sub_variable_scores (ADR-004a §2).

from collections.abc import Mapping
from typing import Any

import psycopg
from psycopg.rows import dict_row

DATABASE_URL_VARIABLE = "NV_SIGNALS_DATABASE_URL"

# Rows come back as dicts (column name → value), which is what the input loader passes on.
DatabaseConnection = psycopg.Connection[dict[str, Any]]


class DatabaseSettingsError(Exception):
    """The environment does not say how to reach the database."""


def connect_to_database(environment: Mapping[str, str]) -> DatabaseConnection:
    """Open a connection using NV_SIGNALS_DATABASE_URL from the given environment.

    Input: the process environment. Output: an open autocommit connection whose rows are dicts.
    Raises DatabaseSettingsError when the variable is missing or empty.
    Implements ADR-004a §2 — the signal service connects as its own nv_signals login.
    """
    database_url = environment.get(DATABASE_URL_VARIABLE, "")
    if not database_url:
        raise DatabaseSettingsError(f"set {DATABASE_URL_VARIABLE}, e.g. postgresql://user@host/db")
    # autocommit: each `with connection.transaction()` block then commits as it ends. Without
    # it, the first query opens a transaction and those blocks become savepoints, so a chunk
    # would not be committed before its batch_written message goes out (ADR-004a §3).
    return psycopg.connect(database_url, row_factory=dict_row, autocommit=True)
