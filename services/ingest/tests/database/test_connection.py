# Checks that the ingest service refuses to guess where the database is.

import pytest

from database.connection import DatabaseSettingsError, connect_to_database


def test_missing_database_url_raises_a_settings_error() -> None:
    environment_without_url: dict[str, str] = {}

    with pytest.raises(DatabaseSettingsError, match="NV_INGEST_DATABASE_URL"):
        connect_to_database(environment_without_url)
