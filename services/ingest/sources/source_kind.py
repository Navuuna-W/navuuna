# The five kinds of source that core.sources accepts. The table's CHECK constraint holds the
# same list (migration create_core_sources_table), so a kind missing here cannot be stored.

from enum import StrEnum


class SourceKind(StrEnum):
    """How data from a source reaches Navuuna (Bible §10, core.sources.kind)."""

    SCRAPE = "scrape"
    RASTER = "raster"
    VECTOR = "vector"
    UPLOAD = "upload"
    COMMUNITY = "community"
