# Module — land

Status: **disabled for v1** (FR-21 is a Phase 2 "could"). This file holds the one land rule
v1 needs: how OSM buildings are loaded. Refs: D-04, DEC-07, FR-01, NFR-03.

## Buildings rule (DEC-07)

**OSM buildings are loaded as `parcel` entities with `module = land`, and the land module is
disabled.**

What that means in practice:

| Step | Buildings… |
|---|---|
| Base-layer load (D-05) | are loaded, with `external_ref` `osm:w<id>` / `osm:r<id>`, and count toward the FR-01 entity total. |
| Signal-service runner | are skipped — the runner only picks up entities whose module is enabled. |
| Rollup | produce no `scores.variable_scores` rows. |
| Default map tiles | are not drawn. The tile query filters to enabled modules. |
| API `/entities` | are returned only when asked for explicitly (`module=land`). |

Why:

- **NFR-03.** Nairobi has hundreds of thousands of building footprints. Running 28 sub-variables
  over them would push the full rollup well past 10 minutes and add nothing to 7 Oct.
- **Honest output.** A building with no land adapters would show five "Not measured" variables.
  Leaving it out of the runner is better than scoring it empty.
- **No rework later.** Buildings already have stable IDs, so when FR-21 lands, enabling
  `land` scores them without a reload.

How "disabled" is decided: a module is enabled when its adapters are registered with the
runner. `land` registers none in v1. No per-entity flag is needed.

## Reload behaviour

Same as every OSM layer (D-05): idempotent upsert on `external_ref`; a building missing from a
newer extract gets `retired_at` and is never deleted (E15).
