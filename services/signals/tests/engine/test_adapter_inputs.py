# Tests for build_adapter_inputs: an adapter sees exactly what its spec declares (ADR-003).

from datetime import UTC, datetime

from engine.adapter_inputs import InputRows, build_adapter_inputs
from engine.adapter_spec import AdapterSpec
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

AS_OF = datetime(2026, 10, 7, 12, 0, tzinfo=UTC)
ENTITY_ROW = {"id": "0192a000-0000-7000-8000-000000000001", "entity_type": "point"}
OBSERVATION_ROWS: InputRows = ({"id": "0192a000-0000-7000-8000-000000000002"},)
NOT_MEASURED = ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="No observations")


def existence_gap_spec() -> AdapterSpec:
    # Asks for the entity and its records, and reads the 1.1 result.
    return AdapterSpec(
        module="water",
        sub_id="2.1",
        entity_types=["point"],
        version="1.0.0",
        requires=[InputKind.ENTITY, InputKind.RECORDS],
        depends_on_sub_ids=["1.1"],
        signal_description="Whether a matched official record has no observed counterpart.",
    )


def test_kinds_the_spec_does_not_ask_for_are_left_out() -> None:
    loaded_rows = {InputKind.OBSERVATIONS: OBSERVATION_ROWS}

    inputs = build_adapter_inputs(existence_gap_spec(), AS_OF, ENTITY_ROW, loaded_rows, {})

    assert inputs.observations is None
    assert inputs.eo_stats is None
    assert inputs.nearby_entities is None
    assert inputs.nearby_ways is None
    assert inputs.same_area_entities is None


def test_a_declared_kind_with_no_rows_becomes_an_empty_tuple() -> None:
    loaded_rows: dict[InputKind, InputRows] = {}

    inputs = build_adapter_inputs(existence_gap_spec(), AS_OF, ENTITY_ROW, loaded_rows, {})

    assert inputs.records == ()


def test_the_entity_row_is_passed_when_declared() -> None:
    loaded_rows: dict[InputKind, InputRows] = {}

    inputs = build_adapter_inputs(existence_gap_spec(), AS_OF, ENTITY_ROW, loaded_rows, {})

    assert inputs.entity == ENTITY_ROW
    assert inputs.as_of == AS_OF


def test_the_entity_row_is_left_out_when_not_declared() -> None:
    spec = existence_gap_spec().model_copy(update={"requires": frozenset({InputKind.RECORDS})})

    inputs = build_adapter_inputs(spec, AS_OF, ENTITY_ROW, {}, {})

    assert inputs.entity is None


def test_only_results_of_declared_dependencies_are_passed() -> None:
    earlier_results = {"1.1": NOT_MEASURED, "1.2": NOT_MEASURED}

    inputs = build_adapter_inputs(existence_gap_spec(), AS_OF, ENTITY_ROW, {}, earlier_results)

    assert inputs.sub_variable_results == {"1.1": NOT_MEASURED}


def test_nearby_rows_beyond_the_adapters_own_radius_are_left_out() -> None:
    spec = existence_gap_spec().model_copy(
        update={"requires": frozenset({InputKind.NEARBY_WAYS}), "nearby_radius_m": 200.0}
    )
    near_way = {"id": "near", "distance_m": 150.0}
    far_way = {"id": "far", "distance_m": 450.0}  # loaded for another adapter's 500 m
    loaded_rows: dict[InputKind, InputRows] = {InputKind.NEARBY_WAYS: (near_way, far_way)}

    inputs = build_adapter_inputs(spec, AS_OF, ENTITY_ROW, loaded_rows, {})

    assert inputs.nearby_ways == (near_way,)
