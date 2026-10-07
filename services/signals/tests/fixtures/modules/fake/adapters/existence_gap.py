# Fake 2.1 Existence gap adapter for engine tests. It depends on 1.1, so the registry must
# order it after presence.py, and the runner must hand it the 1.1 result.

from engine.adapter_inputs import AdapterInputs
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

PRESENCE_SUB_ID = "1.1"

SPEC = AdapterSpec(
    module="fake",
    sub_id="2.1",
    entity_types=[EntityType.POINT],
    version="1.0.0",
    requires=[InputKind.ENTITY, InputKind.RECORDS],
    depends_on_sub_ids=[PRESENCE_SUB_ID],
    signal_description="Fake existence gap: never measured; only its order and inputs matter.",
)


def score(inputs: AdapterInputs) -> ScoreResult:
    """Always null_not_measured, with a reason that shows whether records were passed in."""
    if not inputs.records:
        return ScoreResult(
            status=ScoreStatus.NULL_NOT_MEASURED, null_reason="No official record found"
        )
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="Fake adapter")
