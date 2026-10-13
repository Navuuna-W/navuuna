# Writes one base layer into core.entities (work pack D-05). Runs after a mapping file has
# turned map features into entity rows. Reloading a layer keeps every entity's ID and retires
# the entities the new file no longer has.

import json
from collections.abc import Sequence
from datetime import date

from pydantic import BaseModel, ConfigDict

from base_layers.entity_row import EntityRow
from database.connection import DatabaseConnection

# Every stored geometry is longitude/latitude, SRID 4326 (Bible §10). Layer files are too.
STORAGE_SRID = 4326

# The new file's entities wait here while the layer is compared with what is already stored.
# ON COMMIT DROP: the table disappears when the load's transaction ends. The DROP first covers
# a caller that loads two layers inside one transaction of its own, as the tests do.
DROP_STAGED_ENTITIES_SQL = "DROP TABLE IF EXISTS staged_entities"

CREATE_STAGED_ENTITIES_SQL = """
    CREATE TEMPORARY TABLE staged_entities (
        entity_type text, module text, name text, external_ref text,
        geometry_geojson text, radius_m double precision, metadata jsonb
    ) ON COMMIT DROP
"""

COPY_STAGED_ENTITIES_SQL = """
    COPY staged_entities
        (entity_type, module, name, external_ref, geometry_geojson, radius_m, metadata)
    FROM STDIN
"""

# The staged GeoJSON text as a PostGIS geometry in the storage SRID.
STAGED_GEOMETRY_SQL = (
    "public.ST_SetSRID(public.ST_GeomFromGeoJSON(geometry_geojson), %(storage_srid)s)"
)

# A few OSM features have a broken shape, e.g. a building whose outline has two corners.
# PostGIS cannot measure or draw those, so they are counted and left out, never repaired
# by guessing. They stay in staged_entities, so an entity stored earlier is not retired.
COUNT_BROKEN_GEOMETRIES_SQL = f"""
    SELECT count(*) AS broken_count
    FROM staged_entities
    WHERE NOT public.ST_IsValid({STAGED_GEOMETRY_SQL})
"""

# `external_ref` is unique, so an entity loaded before is updated in place and keeps its id
# (FR-02). `retired_at = NULL` brings back an entity that had dropped out of an earlier file.
# A point entity must be stored as a point (Bible §5), but OSM draws some water points as an
# outline, e.g. a water works. ST_PointOnSurface gives a point inside the outline, and leaves
# a feature that already is a point where it was.
UPSERT_ENTITIES_SQL = f"""
    INSERT INTO core.entities (entity_type, module, name, external_ref, geom, radius_m, metadata)
    SELECT entity_type, module, name, external_ref,
           CASE WHEN entity_type = 'point' THEN public.ST_PointOnSurface(feature_geom)
                ELSE feature_geom
           END,
           radius_m, metadata
    FROM (SELECT *, {STAGED_GEOMETRY_SQL} AS feature_geom FROM staged_entities) AS staged
    WHERE public.ST_IsValid(feature_geom)
    ON CONFLICT (external_ref) DO UPDATE SET
        entity_type = EXCLUDED.entity_type,
        module = EXCLUDED.module,
        name = EXCLUDED.name,
        geom = EXCLUDED.geom,
        radius_m = EXCLUDED.radius_m,
        metadata = EXCLUDED.metadata,
        retired_at = NULL
"""

# Entities are retired, never deleted (E15, ADR-004a §2). Only this layer's own entities are
# looked at, so loading wards can never retire a water point.
RETIRE_MISSING_ENTITIES_SQL = """
    UPDATE core.entities AS stored
    SET retired_at = now()
    WHERE stored.metadata ->> 'base_layer' = %(layer_name)s
      AND stored.retired_at IS NULL
      AND NOT EXISTS (
          SELECT 1 FROM staged_entities AS staged
          WHERE staged.external_ref = stored.external_ref
      )
"""

SELECT_SOURCE_ID_SQL = "SELECT id FROM core.sources WHERE name = %(source_name)s"


class BaseLayerLoadError(Exception):
    """The layer cannot be loaded as given; nothing was written."""


class BaseLayer(BaseModel):
    """One layer file ready to load: where it came from and the entities it holds."""

    model_config = ConfigDict(frozen=True)

    # Stored on every entity as metadata.base_layer; see the layer name constants.
    layer_name: str
    # The `name` of the layer's row in core.sources (sources/source_catalogue.py).
    source_name: str
    # The date of the extract. Adapters read it as the date the map last saw the entity.
    extract_date: date
    entity_rows: Sequence[EntityRow]


class BaseLayerLoadResult(BaseModel):
    """What one load did: how many entities it wrote, left out and retired."""

    model_config = ConfigDict(frozen=True)

    loaded_count: int
    # Entities left out because PostGIS says their geometry is broken.
    skipped_count: int
    retired_count: int


def load_base_layer(connection: DatabaseConnection, base_layer: BaseLayer) -> BaseLayerLoadResult:
    """Store a layer's entities and retire the ones the layer no longer has.

    Inputs: an open connection and the layer. Output: the loaded, skipped and retired counts.
    An entity whose geometry is broken is skipped: counted, not stored, not repaired.
    Runs in one transaction, so a failed load leaves the stored layer exactly as it was.
    Raises BaseLayerLoadError for an empty layer, a repeated external reference, or a source
    that is not in core.sources.
    Implements FR-01 (base layers as entities), FR-02 (stable IDs across reloads) and
    E15 (entities missing from a new extract are retired, never deleted).
    """
    check_layer_can_be_loaded(base_layer)
    storage_srid = {"storage_srid": STORAGE_SRID}
    with connection.transaction():
        source_id = find_source_id(connection, base_layer.source_name)
        stage_entities(connection, base_layer, source_id)
        broken = connection.execute(COUNT_BROKEN_GEOMETRIES_SQL, storage_srid).fetchone()
        upserted = connection.execute(UPSERT_ENTITIES_SQL, storage_srid)
        retired = connection.execute(
            RETIRE_MISSING_ENTITIES_SQL, {"layer_name": base_layer.layer_name}
        )
    return BaseLayerLoadResult(
        loaded_count=upserted.rowcount,
        skipped_count=0 if broken is None else broken["broken_count"],
        retired_count=retired.rowcount,
    )


def check_layer_can_be_loaded(base_layer: BaseLayer) -> None:
    """Raise BaseLayerLoadError when the layer is empty or repeats an external reference."""
    if not base_layer.entity_rows:
        # An empty file is a broken extract, not an empty city: loading it would retire
        # every entity of the layer.
        raise BaseLayerLoadError(f"layer '{base_layer.layer_name}' has no entities")
    seen_external_refs: set[str] = set()
    for entity_row in base_layer.entity_rows:
        if entity_row.external_ref in seen_external_refs:
            raise BaseLayerLoadError(
                f"layer '{base_layer.layer_name}' has '{entity_row.external_ref}' twice"
            )
        seen_external_refs.add(entity_row.external_ref)


def find_source_id(connection: DatabaseConnection, source_name: str) -> str:
    """Return the id of the core.sources row with this name, as text."""
    row = connection.execute(SELECT_SOURCE_ID_SQL, {"source_name": source_name}).fetchone()
    if row is None:
        raise BaseLayerLoadError(
            f"source '{source_name}' is not in core.sources; "
            "run `python -m sources.register_sources` first"
        )
    return str(row["id"])


def stage_entities(connection: DatabaseConnection, base_layer: BaseLayer, source_id: str) -> None:
    """Copy the layer's entities into the temporary staged_entities table."""
    connection.execute(DROP_STAGED_ENTITIES_SQL)
    connection.execute(CREATE_STAGED_ENTITIES_SQL)
    with connection.cursor() as cursor, cursor.copy(COPY_STAGED_ENTITIES_SQL) as copy:
        for entity_row in base_layer.entity_rows:
            copy.write_row(
                (
                    entity_row.entity_type.value,
                    entity_row.module,
                    entity_row.name,
                    entity_row.external_ref,
                    json.dumps(entity_row.geometry),
                    entity_row.radius_m,
                    json.dumps(build_stored_metadata(entity_row, base_layer, source_id)),
                )
            )


def build_stored_metadata(
    entity_row: EntityRow, base_layer: BaseLayer, source_id: str
) -> dict[str, object]:
    """Add where the entity came from to what the source said about it.

    core.entities has no source column, so the adapters read `source_id` and `observed_at`
    from metadata (docs/modules/water.md, "Inputs"). `base_layer` is how a reload finds the
    layer's earlier entities.
    """
    return {
        **entity_row.metadata,
        "base_layer": base_layer.layer_name,
        "source_id": source_id,
        "observed_at": base_layer.extract_date.isoformat(),
    }
