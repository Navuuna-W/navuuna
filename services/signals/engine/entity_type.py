# The four kinds of scored entity (Bible §5, §10: core.entities.entity_type).
# Adapters declare which of these they can score; the runner only calls an adapter
# for entities of a declared type.

from enum import StrEnum


class EntityType(StrEnum):
    """The shape of a scored entity. Values match `core.entities.entity_type` exactly."""

    POINT = "point"  # e.g. a water tap, kiosk or borehole
    PARCEL = "parcel"  # a land parcel
    SEGMENT = "segment"  # a stretch of road
    AREA = "area"  # a ward or neighbourhood — density and safety are scored here
