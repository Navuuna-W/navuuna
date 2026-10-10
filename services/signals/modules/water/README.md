# modules/water/

The water module: one pure adapter per sub-variable for water points (taps, kiosks, wells,
boreholes, tanks, reservoirs, treatment works, toilets). Owner: Devyan (Signal lane).
The spec every adapter here follows is `docs/modules/water.md`; change the spec first.

| File | Sub-variable |
|---|---|
| `adapters/presence.py` | 1.1 Presence (V1 gate) |

The registry (`engine/registry.py`) finds every `adapters/*.py` not starting with `_`.
`MODULES_ENABLED=water` runs only this module. Deleting this folder must not break the
build (NFR-07).

## Test

From `services/signals`:

    pytest tests/modules/water
    mypy --strict modules
    ruff check modules tests/modules
