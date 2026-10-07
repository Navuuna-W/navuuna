# Fake 5.2 Connection quality adapter in a second module, so tests can switch one module off
# with MODULES_ENABLED and check the other still loads.

from engine.adapter_inputs import AdapterInputs
from engine.adapter_spec import AdapterSpec
from engine.entity_type import EntityType
from engine.input_kind import InputKind
from engine.score_result import ScoreResult, ScoreStatus

SPEC = AdapterSpec(
    module="other",
    sub_id="5.2",
    entity_types=[EntityType.POINT, EntityType.SEGMENT],
    version="0.1.0",
    requires=[InputKind.NEARBY_WAYS],
    nearby_radius_m=200,
    signal_description="Fake connection quality: never measured.",
)


def score(inputs: AdapterInputs) -> ScoreResult:
    """Always null_not_measured."""
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="Fake adapter")
