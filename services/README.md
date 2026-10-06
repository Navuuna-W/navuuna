# services/

The Python side of Navuuna (Bible §14.1). Each folder is one service:

- `signals/` — the signal service. `signals/engine/` is the module-agnostic runner and rollup
  (Core lane); `signals/modules/` holds the per-module adapters (Signal lane).
- `ingest/` and `flows/` arrive with their tasks (D-07, D-22, K-12).

Right now only `signals/engine/weights.yml` exists: the fixed sub-variable weights the rollup
reads (Bible §6.7). Check it with:

    python3 -c "import yaml; w = yaml.safe_load(open('services/signals/engine/weights.yml')); print({variable: round(sum(weights.values()), 2) for variable, weights in w.items() if variable != 'weight_version'})"

Every variable must print 1.0. Tooling (pytest, mypy, ruff) lands with K-09.
