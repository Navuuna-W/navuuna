# tests/fixtures/

Test data for the signal engine. Nothing here runs in production.

- `modules/` — a stand-in for `services/signals/modules/`, with two fake modules:
  - `fake/` — 1.1 Presence and 2.1 Existence gap (2.1 depends on 1.1).
  - `other/` — 5.2 Connection quality, so tests can switch one module off.

  They stand in for Devyan's real water adapters until those land (D-18). They also show the
  shape of an adapter file: a `SPEC = AdapterSpec(...)` and a pure `score(inputs)` function.

Tests point the registry at this folder: `discover_adapters(FIXTURE_MODULES_ROOT, environment)`.
