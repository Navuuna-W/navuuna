# lenses/

Lens files: a customer's point of view on the five variable scores (Bible §6.6, FR-12).
`php artisan lens:sync` (Access lane, A-07) validates each file here and loads it into
`lens.lenses`, versioned. Content owner: Devyan (Signal lane). Refs: D-10, DEC-15.

## Why one folder at the repo root (DEC-15)

The Bible suggests a default `lens.json` per module. A lens is about a **customer**, not a
module: the county planner looks at water points, wards and roads in one map. So lenses live
in one place, one file per customer type. Modules ship no lens.

## What a lens file may contain — and nothing else

| Key | Meaning |
|---|---|
| `variable_weights` | Weights across V1–V5. Must sum to 1.00. A variable at 0 is still shown in the Passport, just not weighted. |
| `direction` | Per variable: `higher_is_better` or `lower_is_better`. `lower_is_better` means the applier uses `100 − score`. V2 is `lower_is_better`: a high V2 score is a big record-vs-reality gap. |
| `thresholds.bands` | Lens score → `colour_class` `b1`–`b4` (80–100, 60–79, 40–59, 0–39). Each band has only a `min`; the first band whose `min` ≤ score wins, so there are no gaps between bands. |
| `thresholds.provisional_suffix` | `p` is appended when the lens score includes a provisional variable (`b2p`); the map hatches it. |
| `thresholds.cannot_assess_class` | `ca` when every weighted variable is `cannot_assess`. |

Never allowed: a `sub_weights` key or any sub-variable weight (ADR-007, CLAUDE.md §4). Sub-variable
weights belong to the engine (`services/signals/engine/weights.yml`).

## Check a file

    python3 -c "import json; lens = json.load(open('lenses/county_planner.json')); print(round(sum(lens['variable_weights'].values()), 2), 'sub_weights' in lens)"

Expected: `1.0 False`.
