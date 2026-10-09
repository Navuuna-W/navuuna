# services/flows

Prefect flows: the jobs that run on a schedule, with retries and a run history (Bible §8.1,
work pack K-12). Prefect is pinned to 3.x for every flow here (ADR-014), because a self-hosted
Prefect server only accepts clients on its own major version.

| File | What it is |
|---|---|
| `score_all.py` | Core's nightly flow: runs `python -m engine run --all` in `services/signals`. It retries twice, then the run shows as Failed. The rollup is not called here: it reacts to `signals.batch_written` on its own (ADR-004a §3). |
| `prefect.yaml` | The deployments: which flow runs, on which work pool, on what schedule. |
| `tests/` | pytest tests. They start a throwaway Prefect server and a fake signal service. |

Devyan's ingest flows (D-22) go in this folder too, as `ingest_*.py`, with the same Prefect pin.
Add each new flow to `prefect.yaml` and to `py-modules` in `pyproject.toml`.

## Run it

In the local stack, `prefect-server` (UI on http://localhost:4200) and `prefect-worker` start
with everything else (see `infra/README.md`). The worker creates the `scoring` work pool and
registers the deployments each time it starts. To run the scoring flow now instead of at 02:00:

    docker compose -f infra/docker-compose.yml exec prefect-worker prefect deployment run 'score_all/score-all' --watch

## Test it

    cd services/flows
    python3.12 -m venv .venv && .venv/bin/pip install -e '.[dev]'
    .venv/bin/pytest
    .venv/bin/mypy --strict .
    .venv/bin/ruff check . && .venv/bin/ruff format --check .
