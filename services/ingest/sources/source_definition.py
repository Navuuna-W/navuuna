# The shape of one entry in the source catalogue: one place data comes from, with the licence
# and attribution text the map footer and the API show. Each entry becomes one core.sources row.

from pydantic import BaseModel, ConfigDict, Field

from sources.source_kind import SourceKind


class SourceDefinition(BaseModel):
    """One source of data, as it will be stored in core.sources.

    Inputs: the source's name, kind, licence and attribution; a base URL when it has one.
    Rejects an empty name, licence or attribution, because the table does too and the
    footer must never show a blank credit.
    Implements Bible §10 (core.sources) and NFR-09 (attribution).
    """

    model_config = ConfigDict(frozen=True)

    # Loaders look their source up by this name, so it must never change once rows exist.
    name: str = Field(min_length=1)
    kind: SourceKind
    # None for sources with no web address, such as what people tell us themselves.
    base_url: str | None = None
    licence: str = Field(min_length=1)
    attribution: str = Field(min_length=1)
    # False for a source we know about but do not load yet.
    is_active: bool = True
