# Writes the source catalogue into core.sources (work pack D-06). Run it once after the
# migrations and again whenever the catalogue changes: `python -m sources.register_sources`.
# Loaders, the map footer and the API then read licence and attribution from those rows.

import os
import sys
from collections.abc import Sequence

from database.connection import DatabaseConnection, DatabaseSettingsError, connect_to_database
from sources.source_catalogue import SOURCE_CATALOGUE
from sources.source_definition import SourceDefinition

# `name` is unique in core.sources, so a second run updates the row instead of adding one.
# The catalogue wins on every column it holds; `schedule` is not in it and is left alone.
UPSERT_SOURCE_SQL = """
    INSERT INTO core.sources (name, kind, base_url, licence, attribution, active)
    VALUES (%(name)s, %(kind)s, %(base_url)s, %(licence)s, %(attribution)s, %(active)s)
    ON CONFLICT (name) DO UPDATE SET
        kind = EXCLUDED.kind,
        base_url = EXCLUDED.base_url,
        licence = EXCLUDED.licence,
        attribution = EXCLUDED.attribution,
        active = EXCLUDED.active
"""

EXIT_OK = 0
EXIT_SETTINGS_ERROR = 1


def register_sources(connection: DatabaseConnection, sources: Sequence[SourceDefinition]) -> int:
    """Insert or refresh one core.sources row per source and return how many were written.

    Inputs: an open connection and the sources to store. Output: the number of sources.
    Runs in one transaction, so either every source is stored or none is.
    Implements Bible D1.4 (core.sources populated) and NFR-09 (attribution).
    """
    with connection.transaction():
        for source in sources:
            upsert_source(connection, source)
    return len(sources)


def upsert_source(connection: DatabaseConnection, source: SourceDefinition) -> None:
    """Insert one source, or update its row when a source with that name already exists."""
    connection.execute(
        UPSERT_SOURCE_SQL,
        {
            "name": source.name,
            # A plain string: psycopg would send an Enum by its name ("VECTOR"), not its value.
            "kind": source.kind.value,
            "base_url": source.base_url,
            "licence": source.licence,
            "attribution": source.attribution,
            "active": source.is_active,
        },
    )


def main() -> int:
    """Store the whole catalogue using NV_INGEST_DATABASE_URL. Output: the process exit code."""
    try:
        connection = connect_to_database(os.environ)
    except DatabaseSettingsError as error:
        print(f"Cannot register sources: {error}", file=sys.stderr)
        return EXIT_SETTINGS_ERROR
    with connection:
        source_count = register_sources(connection, SOURCE_CATALOGUE)
    print(f"Registered {source_count} sources in core.sources")
    return EXIT_OK


if __name__ == "__main__":
    sys.exit(main())
