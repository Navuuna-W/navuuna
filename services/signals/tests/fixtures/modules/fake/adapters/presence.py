# Fake 1.1 Presence adapter for engine tests — stands in for Devyan's water adapters (D-18).
# It also shows the shape every adapter file has: a SPEC and a pure score() function.

from uuid import UUID

from engine.adapter_inputs import AdapterInputs
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

PRESENT_SCORE = 100.0
FAKE_CONFIDENCE = 0.8

SPEC = AdapterSpec(
    module="fake",
    sub_id="1.1",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.OBSERVATIONS],
    signal_description="Fake presence: measured when the entity has any observation.",
)


def score(inputs: AdapterInputs) -> ScoreResult:
    """Score 100 when there is at least one observation; otherwise null_not_measured."""
    if not inputs.observations:
        return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="No observations")
    newest_observation = inputs.observations[0]
    return ScoreResult(
        status=ScoreStatus.MEASURED,
        value="present",
        score=PRESENT_SCORE,
        confidence=FAKE_CONFIDENCE,
        observed_at=inputs.as_of,
        source_ids=(UUID(str(newest_observation["id"])),),
    )
