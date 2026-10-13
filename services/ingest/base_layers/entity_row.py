# The shape of one entity on its way into core.entities. The mapping files (area_entities.py
# and the OSM ones) build these from map features; load_base_layer.py writes them.

from typing import Any

from pydantic import BaseModel, ConfigDict, Field

from base_layers.entity_type import EntityType


class EntityRow(BaseModel):
    """One entity from a base layer, as it will be stored in core.entities.

    Inputs: the entity's type, name, external reference and geometry; a module unless it is
    a shared area; a radius only for a point. Rejects an empty name or external reference,
    because the table does too.
    Implements Bible §5 (entity types), Bible §10 (core.entities) and FR-02 (stable IDs).
    """

    model_config = ConfigDict(frozen=True)

    entity_type: EntityType
    # None for shared areas such as wards, which belong to no single module.
    module: str | None = None
    name: str = Field(min_length=1)
    # The ID in the source data set, e.g. `osm:n123` or `iebc:ward:KE0017`. It is what keeps
    # an entity the same entity across reloads, so it must never be made up.
    external_ref: str = Field(min_length=1)
    # A GeoJSON geometry object in longitude/latitude (SRID 4326), exactly as the file had it.
    geometry: dict[str, Any]
    # Metres. Required for a point and forbidden for every other type (Bible §5).
    radius_m: float | None = None
    # What the source said about the entity. A tag the source did not have is left out,
    # never filled with a default (Bible §6.1: unmeasured is not zero).
    metadata: dict[str, Any] = Field(default_factory=dict)
