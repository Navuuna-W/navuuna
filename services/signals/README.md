# services/signals/

The signal service (Bible §14.1). Adapters turn one entity's inputs into one sub-variable
score; the engine runs them and rolls the scores up into the five variables.

- `engine/` — module-agnostic contracts, runner and rollup (Core lane). Knows the
  sub-variable IDs (`sub_variables.py`), entity types (`entity_type.py`) and the weights
  (`weights.yml`). Knows nothing about water or roads.
  Adapter contract (ADR-003): every adapter carries an `AdapterSpec` (`adapter_spec.py`,
  inputs from `input_kind.py`) and returns a `ScoreResult` (`score_result.py`).
  The registry (`registry.py`) finds every adapter, skips modules switched off in
  `MODULES_ENABLED` (`module_flags.py`) and returns them in dependency order.
  The runner (`runner.py`) loads each entity's inputs (`input_loader.py`), calls the adapters
  that fit its type and appends valid results to `scores.sub_variable_scores` (`score_writer.py`).
- `modules/` — per-module adapters, e.g. `modules/water/adapters/` (Signal lane, arrives with D-18).
- `tests/` — pytest; `tests/engine/` mirrors `engine/`; `tests/fixtures/modules/` holds fake
  adapters for engine tests.

## Writing an adapter

One file per sub-variable in `modules/<module>/adapters/`, e.g. `modules/water/adapters/presence.py`.
Files starting with `_` are helpers and are not loaded as adapters. Each adapter file defines:

    SPEC = AdapterSpec(module="water", sub_id="1.1", entity_types=["point"], version="1.0.0",
                       requires=["observations"], signal_description="...")

    def score(inputs: AdapterInputs) -> ScoreResult: ...

`inputs` (`engine/adapter_inputs.py`) holds `as_of` and only the input kinds listed in
`requires` (others are `None`); rows are plain JSON dicts. `inputs.sub_variable_results` holds
the results of `depends_on_sub_ids`. An adapter that requires `nearby_entities` or `nearby_ways` sets
`nearby_radius_m` (≤ 5000); those rows carry `distance_m`, nearest first. See `tests/fixtures/modules/fake/adapters/` for examples.

`MODULES_ENABLED=water,roads` limits which modules run; unset means every module runs.

## Set up

    cd services/signals
    python3 -m venv .venv
    .venv/bin/pip install -e '.[dev]'

Needs Python 3.12 or newer.

## Test, type-check, lint

    .venv/bin/pytest --cov=engine --cov-fail-under=100   # Bible §14.7: 100 % of engine/
    .venv/bin/mypy --strict engine
    .venv/bin/ruff check . && .venv/bin/ruff format --check .

Stream tests are skipped unless `NV_TEST_REDIS_URL` points at a Redis (e.g. `redis://127.0.0.1:6379/0`);
each test uses its own stream and deletes it. Database tests are skipped unless `NV_TEST_DATABASE_URL` points at a PostgreSQL that has the
Laravel migrations applied (`cd apps/api && php artisan migrate`). Each test is rolled back.
CI always sets it, so skipped database tests show up there as missing coverage.

    NV_TEST_DATABASE_URL=postgresql://navuuna:navuuna@127.0.0.1:5432/navuuna_test .venv/bin/pytest

## Running

    NV_SIGNALS_DATABASE_URL=... NV_REDIS_URL=... .venv/bin/python -m engine run --all
    .venv/bin/python -m engine run --module water        # repeat --module for several

A run finds the adapters, records them in `core.adapters`, scores every active entity of each
module in chunks of 500 (one committed transaction each) and announces each chunk on
`signals.batch_written`. `--modules-root` points at another modules folder (e.g.
`tests/fixtures/modules`). Exit code 0 on success, 1 on a missing setting, unknown module or
broken adapter. Prefect (K-12) runs this command.

    .venv/bin/python -m engine consume --consumer-name box-b-1

`consume` reads `signals.recompute_requested` as consumer group `runner`: it rescores each
requested entity, announces it on `signals.batch_written`, and only then acknowledges the
request (ADR-004a §3). Supervisor keeps it running; SIGTERM stops it cleanly. Each running
reader needs its own `--consumer-name` (default: the hostname).

## Configuration

- `NV_SIGNALS_DATABASE_URL` — the service's database login, a member of `nv_signals` (ADR-004a).
- `NV_REDIS_URL` — the Box A Redis that carries `signals.batch_written` (ADR-004a §3).
