# The kinds of input an adapter can ask the runner for (ADR-003, Bible §14.6).
# An adapter lists what it needs in AdapterSpec.requires; the runner (K-09b) loads exactly
# those rows for one entity and passes them in. Adapters never fetch anything themselves.

from enum import StrEnum


class InputKind(StrEnum):
    """One kind of input row the runner can load for an entity."""

    ENTITY = "entity"  # the entity's own core.entities row
    OBSERVATIONS = "observations"  # core.observations for the entity (community and other sources)
    RECORDS = "records"  # register records matched to the entity
    EO_STATS = "eo_stats"  # raw.eo_stats satellite statistics for the entity's footprint
    NEARBY_ENTITIES = "nearby_entities"  # other entities within a radius (e.g. 4.4 Competition)
    NEARBY_WAYS = "nearby_ways"  # OSM ways within a radius (e.g. 5.2 Connection quality)
