# The label every adapter carries: which sub-variable it scores, for which entity types,
# and which inputs it needs (ADR-003). The registry (K-09b) reads these labels to fill
# core.adapters; the runner uses `requires` to load exactly those inputs and nothing more.

from typing import Annotated, Self

from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator

from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.sub_variables import SUB_VARIABLE_IDS, is_known_sub_variable

# Lower-case module names such as "water" or "roads" — they become folder names.
MODULE_NAME_PATTERN = r"^[a-z][a-z_]*$"
# Semantic version "major.minor.patch", stored as adapter_version on every score row.
VERSION_PATTERN = r"^\d+\.\d+\.\d+$"
# At least one character that is not a space: core.adapters.signal_description is NOT NULL
# and is shown to analysts, so a blank description is as bad as none.
NOT_BLANK_PATTERN = r"\S"

ModuleName = Annotated[str, Field(pattern=MODULE_NAME_PATTERN)]
AdapterVersion = Annotated[str, Field(pattern=VERSION_PATTERN)]
SignalDescription = Annotated[str, Field(pattern=NOT_BLANK_PATTERN)]


class AdapterSpec(BaseModel):
    """Describes one adapter so the engine can register and run it without knowing its module.

    Fields: module ("water"), sub_id (one of the frozen IDs, "1.2"), entity_types it can score,
    version (semver), requires (at least one InputKind), depends_on_sub_ids — other
    sub-variables of the same entity whose results it reads (e.g. 2.1 reads 1.1) — and
    signal_description, one sentence on what it measures, stored in core.adapters.
    Implements ADR-003 and Bible §6.8 — no adapter can invent a sub-variable.
    """

    # Not strict, unlike ScoreResult: a spec is hand-written config, so ["point"] may become
    # frozenset({EntityType.POINT}). Unknown names are still rejected.
    model_config = ConfigDict(frozen=True, extra="forbid")

    module: ModuleName
    sub_id: str
    entity_types: frozenset[EntityType] = Field(min_length=1)
    version: AdapterVersion
    requires: frozenset[InputKind] = Field(min_length=1)
    depends_on_sub_ids: frozenset[str] = frozenset()
    signal_description: SignalDescription

    @field_validator("sub_id")
    @classmethod
    def check_sub_id_is_known(cls, sub_id: str) -> str:
        if not is_known_sub_variable(sub_id):
            raise ValueError(f"{sub_id!r} is not one of the sub-variables in Bible §6.8")
        return sub_id

    @field_validator("depends_on_sub_ids")
    @classmethod
    def check_dependencies_are_known(cls, depends_on_sub_ids: frozenset[str]) -> frozenset[str]:
        unknown_ids = sorted(depends_on_sub_ids - SUB_VARIABLE_IDS)
        if unknown_ids:
            raise ValueError(f"unknown sub-variables in depends_on_sub_ids: {unknown_ids}")
        return depends_on_sub_ids

    @model_validator(mode="after")
    def check_does_not_depend_on_itself(self) -> Self:
        # The runner orders adapters by their dependencies; a self-dependency would never run.
        if self.sub_id in self.depends_on_sub_ids:
            raise ValueError(f"adapter for {self.sub_id} cannot depend on its own result")
        return self
