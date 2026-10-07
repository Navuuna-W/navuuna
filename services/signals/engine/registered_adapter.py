# One adapter the registry found: its spec, its score function and the file it came from.
# The registry builds these from modules/*/adapters/*.py; the runner calls `score` and stores
# `code_ref` in core.adapters so every score row can be traced back to its source file.

from collections.abc import Callable
from dataclasses import dataclass

from engine.adapter_inputs import AdapterInputs
from engine.adapter_spec import AdapterSpec
from engine.score_result import ScoreResult

# The shape of every adapter: one entity's inputs in, one sub-variable result out (ADR-003).
ScoreFunction = Callable[[AdapterInputs], ScoreResult]


@dataclass(frozen=True)
class RegisteredAdapter:
    """An adapter ready to run.

    spec: the AdapterSpec the file declares. score: the file's pure `score` function.
    code_ref: the file's path from the service root, e.g. "modules/water/adapters/presence.py".
    """

    spec: AdapterSpec
    score: ScoreFunction
    code_ref: str
