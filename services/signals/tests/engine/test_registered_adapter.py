# Tests for RegisteredAdapter: what the runner holds for each adapter the registry found.

from datetime import UTC, datetime

from engine.adapter_inputs import AdapterInputs
from engine.adapter_spec import AdapterSpec
from engine.registered_adapter import RegisteredAdapter
from engine.score_result import ScoreResult, ScoreStatus


def score_nothing(inputs: AdapterInputs) -> ScoreResult:
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="No observations")


def test_the_runner_calls_score_with_the_inputs() -> None:
    spec = AdapterSpec(
        module="water",
        sub_id="1.1",
        entity_types=["point"],
        version="1.0.0",
        requires=["observations"],
        signal_description="Whether the entity is there.",
    )
    adapter = RegisteredAdapter(
        spec=spec, score=score_nothing, code_ref="modules/water/adapters/presence.py"
    )

    result = adapter.score(AdapterInputs(as_of=datetime(2026, 10, 7, tzinfo=UTC), observations=()))

    assert result.null_reason == "No observations"
