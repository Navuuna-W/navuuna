# What the runner hands an adapter: everything it may read for one entity (ADR-003, Bible §14.6).
# The runner loads the rows, then build_adapter_inputs keeps only the kinds the adapter lists
# in AdapterSpec.requires. An adapter therefore cannot read anything it did not ask for.

from collections.abc import Mapping

from pydantic import AwareDatetime, BaseModel, ConfigDict, JsonValue

from engine.adapter_spec import AdapterSpec
from engine.input_kind import InputKind
from engine.score_result import ScoreResult

# One database row as plain JSON: column name → value. Timestamps arrive as ISO 8601 strings.
# Rows stay generic on purpose — the engine must not know water's or roads' columns.
InputRow = dict[str, JsonValue]
InputRows = tuple[InputRow, ...]


class AdapterInputs(BaseModel):
    """Everything one adapter may read for one entity.

    as_of: the moment the run treats as "now" — adapters never read the clock, so a rerun
    gives the same answer. Each input kind is None when the adapter did not ask for it, and
    an empty tuple when it asked and nothing was found. sub_variable_results holds the results
    of the sub-variables listed in depends_on_sub_ids that have already run for this entity.
    Implements ADR-003 and Bible §14.6 — adapters are pure; the runner does the plumbing.
    """

    model_config = ConfigDict(frozen=True, extra="forbid")

    as_of: AwareDatetime
    entity: InputRow | None = None
    observations: InputRows | None = None
    records: InputRows | None = None
    eo_stats: InputRows | None = None
    nearby_entities: InputRows | None = None
    nearby_ways: InputRows | None = None
    same_area_entities: InputRows | None = None
    sub_variable_results: dict[str, ScoreResult] = {}


def build_adapter_inputs(
    spec: AdapterSpec,
    as_of: AwareDatetime,
    entity: InputRow,
    loaded_rows: Mapping[InputKind, InputRows],
    earlier_results: Mapping[str, ScoreResult],
) -> AdapterInputs:
    """Build the inputs for one adapter, keeping only what its spec declares.

    Inputs: the adapter's spec, the run's as_of, the entity row, every row the runner loaded
    for this entity (by kind) and every result already computed for this entity in this run
    (by sub_id). Output: AdapterInputs with undeclared kinds and results left out, and
    nearby rows beyond the adapter's own nearby_radius_m left out.
    Implements ADR-003 — the runner loads exactly what `requires` declares.
    """
    declared_results = {
        sub_id: result
        for sub_id, result in earlier_results.items()
        if sub_id in spec.depends_on_sub_ids
    }
    return AdapterInputs(
        as_of=as_of,
        entity=entity if InputKind.ENTITY in spec.requires else None,
        observations=select_declared_rows(spec, loaded_rows, InputKind.OBSERVATIONS),
        records=select_declared_rows(spec, loaded_rows, InputKind.RECORDS),
        eo_stats=select_declared_rows(spec, loaded_rows, InputKind.EO_STATS),
        nearby_entities=select_nearby_rows(spec, loaded_rows, InputKind.NEARBY_ENTITIES),
        nearby_ways=select_nearby_rows(spec, loaded_rows, InputKind.NEARBY_WAYS),
        same_area_entities=select_declared_rows(spec, loaded_rows, InputKind.SAME_AREA_ENTITIES),
        sub_variable_results=declared_results,
    )


def select_declared_rows(
    spec: AdapterSpec, loaded_rows: Mapping[InputKind, InputRows], kind: InputKind
) -> InputRows | None:
    """Return the loaded rows of one kind, or None when the spec does not ask for that kind.

    A declared kind with nothing loaded becomes an empty tuple: "asked, found nothing" is
    different from "did not ask", and the adapter turns it into null_not_measured.
    """
    if kind not in spec.requires:
        return None
    return loaded_rows.get(kind, ())


def select_nearby_rows(
    spec: AdapterSpec, loaded_rows: Mapping[InputKind, InputRows], kind: InputKind
) -> InputRows | None:
    """Like select_declared_rows, but keep only rows within the adapter's own radius.

    The runner loads nearby rows once, within the largest radius any adapter asked for; an
    adapter that asked for 200 m must not see the rows meant for a 500 m adapter.
    """
    declared_rows = select_declared_rows(spec, loaded_rows, kind)
    if declared_rows is None or spec.nearby_radius_m is None:
        return declared_rows
    radius_m = spec.nearby_radius_m
    return tuple(row for row in declared_rows if is_within_radius(row, radius_m))


def is_within_radius(row: InputRow, radius_m: float) -> bool:
    """True when a nearby row's distance_m (added by the loader) is at most radius_m."""
    distance_m = row["distance_m"]
    return isinstance(distance_m, int | float) and distance_m <= radius_m
