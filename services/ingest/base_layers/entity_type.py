# The four entity types (Bible §5, docs/CONTEXT.md). The values match the CHECK on
# core.entities.entity_type, so a type outside this list cannot be stored.

from enum import StrEnum


class EntityType(StrEnum):
    """The geometry family of an entity: what shape it has on the map."""

    POINT = "point"
    PARCEL = "parcel"
    SEGMENT = "segment"
    AREA = "area"
