# Module — water

Adapter specs for the water module, written before any adapter code (D-12). Khillon's runner
calls these adapters per entity; each returns one sub-variable result (Bible §6.1). Words come
from `docs/CONTEXT.md`. Refs: D-12, FR-07, DEC-16, DEC-23, ADR-003.

## Scope

- **Entities:** `point` entities with `module = water` from OSM (D-05) — taps, kiosks, wells,
  boreholes, storage tanks, reservoirs, treatment works, toilets — plus register records
  matched to them (D-17).
- **For 7 Oct (DEC-23 trim):** 8 adapters, in build order — **1.1, 2.1, 2.2, 2.4, 2.5, 1.2,
  5.2, 4.4**. **After 7 Oct:** 5.1, 4.1, 1.3 (short specs at the end so the design is fixed).
- **Never water-specific in the engine.** Everything below is the adapter's job.

## Rules every water adapter follows

1. **Pure.** Inputs in, one result out. No database calls, no clock: the runner passes
   `as_of` and every input row (ADR-003).
2. **Output:** `value`, `score` (0–100, direction-neutral), `confidence` (0–1), `status`,
   `null_reason`, `observed_at` (of the newest input used), `source_ids` (every input's source).
3. **Never 0 for missing.** Any missing input → `null_not_measured` with one of the reason
   strings listed below, verbatim. Reason strings are user-facing (they show on hover).
4. **E7 — several records:** the most recent record (by record date) feeds the score; the
   others stay in `source_ids` context but do not change the value.
5. **E4 — OSM-only entity:** all of V2 is `null_not_measured`, reason **"No official record
   found"**.
6. **E10 — missing OSM attribute:** null, never a default.
7. **E11 — community vs satellite disagree:** keep both in `source_ids`, use the more recent
   for the value, multiply confidence by `CONTRADICTION_CONFIDENCE_FACTOR = 0.6`.
8. **Record age lowers V2 confidence** (Bible §6.9): every 2.x adapter multiplies its
   confidence by the freshness factor
   `max(MIN_FRESHNESS_FACTOR, 1 − record_age_days / STALE_AFTER_DAYS)` with
   `STALE_AFTER_DAYS = 730`, `MIN_FRESHNESS_FACTOR = 0.3`.

Shared constants: `RECENT_OBSERVATION_DAYS = 90`, `OBSERVATION_LOOKBACK_DAYS = 365`.

## What the adapters read from each input row

The runner passes plain database rows (`engine/input_loader.py`). Column names below are the
real ones in the migrations; the adapters read nothing else.

| Input | Fields read | Notes |
|---|---|---|
| entity (`core.entities`) | `external_ref`, `retired_at`, `metadata.source_id`, `metadata.observed_at` | `osm:n123` / `osm:w456` means the entity is in the OSM extract (D-05). D-05 writes `metadata.source_id` (the `core.sources` row of the extract) and `metadata.observed_at` (the extract date), because `core.entities` has no source column |
| observation (`core.observations`) | `source_id`, `observed_at`, `contributor_id`, `payload` | A ground report is any observation whose `payload` has `existence` (payload v1 below) |
| record (`records.water_schemes`) | `document_id`, `record_date`, `rated_yield_m3d`, `reported_production_m3d`, `status_declared`, `alignment_confidence` | `document_id` goes in `source_ids`: the score traces to the document (Bible §7.3) |
| nearby way | `metadata.surface`, `metadata.highway`, `distance_m` | OSM ways are `segment` entities (D-05) |

---

## 1.1 Presence — gate

| | |
|---|---|
| **Signal** | Is there a water point where the entity says? |
| **Requires** | Entity row (current OSM extract, `retired_at` null) · community observations (DEC-14) |
| **Value** | `present` \| `absent` |
| **Gate result** | `present` → passed · `absent` → failed (V1 `cannot_assess`) |
| **Score** | 100 present · 0 absent (gates only pass/fail; the score is stored for the Passport) |

Rule:
- Present in the current OSM extract → `present`, confidence `0.7`.
- At least `MIN_ABSENCE_REPORTERS = 2` different contributors reported "does not exist here"
  within `RECENT_OBSERVATION_DAYS`, and no "exists" report after them → `absent`, confidence `0.7`.
- One "does not exist here" report only → stays `present`, confidence × `0.6` (DEC-14).
- A recent "exists" community observation → confidence `0.9`.

"Different contributors" means different non-null `contributor_id`s: anonymous reports cannot
prove they are two people. "After them" means after the newest of those absence reports. A
`present` result with any recent "does not exist here" report has its confidence × `0.6`.
`source_ids` hold the extract source (when in OSM) and every report used.

Null: entity comes only from a register record (no OSM node, no observation) →
**"No ground observation of this water point yet"**. The variable becomes `provisional`
(Bible §6.3) — never a finding. In OSM but `metadata.source_id` or `metadata.observed_at` is
missing → **"Map source of this water point not recorded"** (an ingest bug made visible, never a
guessed source).

## 2.1 Existence gap — gate

| | |
|---|---|
| **Signal** | The register says a scheme exists here; we observe it does not. |
| **Requires** | Matched register record (D-17) · result of 1.1 for the same entity |
| **Value** | `gap` \| `no_gap` |
| **Gate result** | `no_gap` → passed · `gap` → failed: V2 `cannot_assess` **and** the existence-gap finding is raised (it is the strongest finding; every other 2.x check is skipped, Bible §6.9) |
| **Score** | 100 gap · 0 no gap |
| **Confidence** | 1.1's confidence × freshness factor |

Null: no matched record → **"No official record found"** (E4) · 1.1 not measured →
**"Presence could not be checked"** (and no V2 finding, Bible §6.3).

For 2.1 and 2.4, a matched record with no `record_date` still counts as a record, with the
freshness factor at `MIN_FRESHNESS_FACTOR`: its age cannot be shown, so it is trusted as
little as the oldest record.

## 2.2 Magnitude gap — contributor

| | |
|---|---|
| **Signal** | Declared capacity vs observed production. |
| **Requires** | Matched register record with `rated_yield_m3d` and `reported_production_m3d` |
| **Value** | `(declared − observed) ÷ declared`, unclipped (negative = producing more than rated) |
| **Score** | `clamp(value, 0, 1) × 100`. **0** = delivers at least what was declared · **100** = delivers nothing of what was declared |
| **Confidence** | `record.alignment_confidence` × freshness factor |

Null: no record → "No official record found" · no rated yield → **"No rated yield in the
register"** · no production figure → **"No production figure in the register"** · rated yield
≤ 0 → **"Rated yield in the register is not a positive number"** · no record date → "No
inspection date in the register" (the record is the only input, so its date is the result's
`observed_at`).

## 2.4 Status gap — contributor

| | |
|---|---|
| **Signal** | Declared operating status vs observed operating state. |
| **Requires** | Matched record `status_declared` · this entity's 1.2 result |
| **Value** | pair `declared → observed`, e.g. `operational → no` |
| **Score** | declared `operational`: observed `yes` 0 · `intermittent` 50 · `no` 100. Declared `not_operational`: observed `no` 0 · otherwise 0 (working when declared broken is not a discrepancy against the public) |
| **Confidence** | `min(record confidence, 1.2 confidence)` × freshness factor |

Null: no record → "No official record found" · no declared status → **"No status in the
register"** (also for a status that is neither `operational` nor `not_operational`) · 1.2 not
measured → **"Current operating state not observed"**. 2.4 treats 1.2 as register-only when
every source of the 1.2 result is a document of a matched record.

## 2.5 Record staleness — contributor

| | |
|---|---|
| **Signal** | How far the record lags behind what we have observed since. |
| **Requires** | Matched record `record_date` (last inspection) · `observed_at` of the newest observation of the entity (any source) |
| **Value** | days = newest observation date − record date (0 if the record is newer) |
| **Score** | `min(days ÷ STALE_AFTER_DAYS, 1) × 100`. **0** = record as new as our observations · **100** = two years or more behind |
| **Confidence** | `0.9` (dates are reliable when present) |

Null: no record → "No official record found" · no record date → **"No inspection date in
the register"** · no observation yet → **"No observation to compare the record with"**.

"Any source" includes the map extract: the entity's `metadata.observed_at` (the extract date)
counts as an observation, with `metadata.source_id` as its source. Observations dated after
`as_of` are ignored.

## 1.2 Operational state — contributor

| | |
|---|---|
| **Signal** | Is it working now? |
| **Requires** | Community observations (DEC-14, within `OBSERVATION_LOOKBACK_DAYS`) · latest matched record `status_declared` · NDWI at outflow (`raw.eo_stats`) **only for reservoirs and treatment works** (footprint large enough for 10 m pixels) |
| **Value** | `yes` \| `intermittent` \| `no` |
| **Score** | yes 100 · intermittent 50 · no 0 |
| **Confidence** | community observation within 90 days `0.8` · older community observation `0.6` · register status only `0.5` × freshness factor · NDWI only `0.4`. E11 contradiction × 0.6 |

Precedence: newest community observation → register → NDWI.
Null: none of the above → **"No recent report of whether this water point works"**.

Adapter version 1.0.0 reads community observations and the register only. The NDWI input is
not read: its wet/dry threshold and how an entity is marked as a reservoir or treatment works
are not decided, and `raw.eo_stats` has no rows until D-13. A contradiction is a report and
the register saying opposite things (`yes` against `no`); `intermittent` contradicts neither,
and the report keeps the value. A register status with no `record_date` is not used alone.

Note: 1.2 feeds 2.4. Using the register's own status as the 1.2 input would compare the
record with itself, so **2.4 is null when 1.2's only input is the register** (reason "Current
operating state not observed").

## 5.2 Connection quality — contributor

| | |
|---|---|
| **Signal** | Condition and class of the path people use to reach it. |
| **Requires** | OSM ways (highway, surface) within `ACCESS_SEARCH_RADIUS_M = 200` |
| **Value** | the nearest way's `surface` (and `highway` class) |
| **Score** | `paved`/`asphalt`/`concrete` 100 · `paving_stones`/`compacted` 75 · `gravel`/`fine_gravel` 60 · `unpaved`/`dirt`/`ground`/`earth`/`mud` 30 |
| **Confidence** | `0.6` (OSM, community-mapped) |

Null (E10, tagging bias, Bible §6.8): no way within 200 m → **"No mapped path within 200 m"**
· nearest way has no `surface` tag → **"Path surface not mapped in OSM"** · unknown surface
value → **"Path surface value not recognised"**. Never default to "unpaved".

## 4.4 Competition — contributor

| | |
|---|---|
| **Signal** | How many other water points draw on the same users and supply nearby. |
| **Requires** | Other water entities (not retired) within `COMPETITION_RADIUS_M = 500` · count of water entities in the same ward |
| **Value** | count of other water points within 500 m |
| **Score** | `min(count ÷ COMPETITION_SATURATION_COUNT, 1) × 100`, `COMPETITION_SATURATION_COUNT = 10`. **0** = no other water point nearby · **100** = 10 or more |
| **Confidence** | `0.5` (OSM completeness varies by ward) |

Null (tagging bias): the ward has fewer than `MIN_MAPPED_WATER_POINTS_IN_WARD = 5` →
**"Too few water points mapped in this ward to judge"**. Under-mapped places must not look
uncontested.

---

## After 7 Oct (moved by DEC-23) — fixed now, built later

**5.1 Proximity (DEC-16).** v1 method: straight-line distance from each residential building
(land parcels, D-04) within 1 km to the water point, × `DETOUR_FACTOR = 1.3`, ÷
`WALKING_SPEED_KM_PER_HOUR = 4.5` → median minutes. Not network-routed, so confidence is
capped at `0.5`. Score: `min(minutes ÷ 30, 1) × 100` (0 = on the doorstep, 100 = 30 min or
more). Null: no buildings mapped within 1 km → "No homes mapped nearby".

**4.1 Availability.** CHIRPS rainfall at the point (D-19). Confidence capped at `0.5`
(Bible §6.8 warning); reason text on every result: **"area-level only"** (E9).

**1.3 Utilisation ratio.** `reported_production ÷ rated_yield` from the register. Null without
both figures, same reason strings as 2.2.

## Community observation payload v1 (DEC-14)

What the observation form (FR-17, Austine) sends for a water entity, stored in
`core.observations` with source kind `community`, and which adapters read it. Refs: D-21,
DEC-14, E11. No PII in the payload: contributor identity stays in the consent record.

| Field | Type | Required | Read by |
|---|---|---|---|
| `entity_id` | uuid | yes | — |
| `observed_at` | timestamptz (when seen, not when sent) | yes | all, for recency |
| `existence` | `exists` \| `does_not_exist_here` | yes | 1.1 |
| `operating_state` | `working` \| `intermittent` \| `not_working` \| `not_sure` | when `exists` | 1.2 (`working`→yes, `intermittent`→intermittent, `not_working`→no; `not_sure` is ignored) |
| `price_per_20_litres_kes` | number ≥ 0 | no | 5.4 (after 7 Oct) |
| `note` | text ≤ 500 chars | no | evidence pack only, never a score |
| `photo_ids` | uuid[] | no | evidence pack only |
| `consent_version` | text | yes | — (NFR-06) |

Rules:
- `does_not_exist_here` lowers 1.1 confidence (× 0.6); two different contributors within 90
  days make 1.1 `absent` (see 1.1).
- E11: when an observation contradicts satellite or register input, keep both in
  `source_ids`, use the newer, multiply confidence by `CONTRADICTION_CONFIDENCE_FACTOR`.
- A withdrawn observation (`POST /observations/{id}/withdraw`, DEC-13) is excluded from the
  next adapter run; scores that used it are recomputed.
- A new observation triggers an adapter re-run for that entity (FR-17).

## Tests each adapter ships with (§14.7)

Known input → known output for every score row above, plus one test per null reason string.
Names as sentences, e.g. `test_missing_surface_tag_is_not_measured_not_unpaved`.
