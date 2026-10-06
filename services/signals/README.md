# services/signals/

The signal service (Bible §14.1). Adapters turn one entity's inputs into one sub-variable
score; the engine runs them and rolls the scores up into the five variables.

- `engine/` — module-agnostic contracts, runner and rollup (Core lane). Knows the
  sub-variable IDs (`sub_variables.py`), entity types (`entity_type.py`) and the weights
  (`weights.yml`). Knows nothing about water or roads.
  Adapter contract (ADR-003): every adapter carries an `AdapterSpec` (`adapter_spec.py`,
  inputs from `input_kind.py`) and returns a `ScoreResult` (`score_result.py`).
- `modules/` — per-module adapters, e.g. `modules/water/adapters/` (Signal lane, arrives with D-18).
- `tests/` — pytest; `tests/engine/` mirrors `engine/`.

## Set up

    cd services/signals
    python3 -m venv .venv
    .venv/bin/pip install -e '.[dev]'

Needs Python 3.12 or newer.

## Test, type-check, lint

    .venv/bin/pytest --cov=engine --cov-fail-under=100   # Bible §14.7: 100 % of engine/
    .venv/bin/mypy --strict engine
    .venv/bin/ruff check . && .venv/bin/ruff format --check .
