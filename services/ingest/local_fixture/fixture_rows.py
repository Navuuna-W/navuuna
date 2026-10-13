# The shapes of the rows that go into the local fixture (work pack D-14): an entity and the
# source it came from. select_fixture_rows.py reads them from a loaded database, and
# write_fixture_sql.py turns them into the SQL file every laptop seeds from.

from typing import Any

from pydantic import BaseModel, ConfigDict


class FixtureSource(BaseModel):
    """One core.sources row the fixture's entities point at."""

    model_config = ConfigDict(frozen=True)

    name: str
    kind: str
    base_url: str | None
    licence: str
    attribution: str


class FixtureEntity(BaseModel):
    """One core.entities row as the fixture stores it."""

    model_config = ConfigDict(frozen=True)

    # The id from the load the fixture was built from, so ids are the same on every laptop.
    id: str
    entity_type: str
    module: str | None
    name: str
    external_ref: str
    # Well-known text in longitude/latitude (SRID 4326).
    geometry_wkt: str
    radius_m: float | None
    metadata: dict[str, Any]
    # The parent area and the source are stored by name, not by id: the SQL file looks both
    # up when it is loaded, so it also works in a database that already has them.
    parent_external_ref: str | None
    source_name: str
