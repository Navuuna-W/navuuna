# Navuuna — CONTEXT (shared vocabulary)

The words code, docs, tickets and the UI use. If a word is here, use it exactly; if it isn't,
don't invent a synonym — add it here first (Bible §14.6). Austine renders the **On-screen
label** columns verbatim (PRD §19). Owner: Devyan (Signal lane). Refs: D-09, DEC-20.

## 1 · Glossary

| Term | Meaning | Not |
|---|---|---|
| **entity** (scored entity) | Anything with a boundary and a persistent identity that we score. One of four types: `point`, `parcel`, `segment`, `area` (Bible §5). | "asset", "site", "feature", "object" |
| **entity type** | The geometry family: `point` (Point + radius), `parcel` (Polygon), `segment` (LineString), `area` (Polygon). | |
| **module** | A domain that owns adapters for some entities: `water`, `roads`, `land`, `energy`, `safety`. Deleting a module folder must not break the build (NFR-07). | "plugin" |
| **variable** | One of the five fixed scores, V1–V5. Rolled up from sub-variables. | "index", "metric" |
| **sub-variable** | One of the frozen measurements under a variable, ID `n.m` (e.g. `1.2`). No new ones (Layer 2 frozen). | "factor", "indicator" |
| **adapter** | A pure function that turns inputs for one entity into one sub-variable result. Knows its domain; the engine does not. | |
| **weight** | Engine-owned share of a sub-variable inside its variable, in `weights.yml`. Changing one needs an ADR. | lens weight |
| **rollup** | Turning sub-variable scores into a variable score, coverage and confidence (Bible §6.2). Missing sub-variables are excluded, never zeroed. | "average" |
| **gate** | A sub-variable that vetoes its variable when it fails: 1.1 Presence, 2.1 Existence gap. Gates veto; contributors average. | |
| **guard** | 2.6 Explanation state. Keeps a finding `held` until a person records whether a legitimate explanation exists. | |
| **contributor** | A weighted sub-variable that is averaged in the rollup (everything except gates and the guard). | |
| **provisional** | Variable status when its gate is unmeasured: available contributors roll up, confidence × 0.5, never a V2 finding. Always shown with a badge. | "estimated" |
| **cannot assess** | Variable status when its gate was measured and failed. No score. | "zero", "N/A" |
| **not measured** | Sub-variable status `null_not_measured`: we could not measure it, and we say why (`null_reason`). Never 0, never a default. | "missing", "0" |
| **coverage** | Share of a variable's weight that was actually measured (0–100 %). Shown beside every score. | |
| **confidence** | How good the inputs were (0–1). Set by the adapter; rolled up with weights. Shown beside every score. | "accuracy" |
| **X of Y** | Measured contributors of all weighted contributors for a variable (DEC-11). Y: V1 4 · V2 4 · V3 6 · V4 6 · V5 6. Gates and the guard are not counted. | |
| **lens** | A customer's point of view: variable weights, direction of goodness, thresholds. Cannot touch sub-variable weights, the rollup or coverage (Bible §6.6). | "profile", "view" |
| **direction of goodness** | Whether high is good or bad for this lens. Lives only in the lens; an adapter that judges good/bad is a bug. | |
| **lens score** | A variable-weighted 0–100 result for one entity under one lens, with coverage and confidence. | "rating" |
| **band** | The lens score range that picks a colour: 80–100, 60–79, 40–59, 0–39. | "grade" |
| **finding** | What V2 produces: a claim that a record and an observation disagree, with an evidence pack and a contest path. Stored as a flag (`flags.*`). The word users see is **finding**. | "alert", "violation" |
| **evidence pack** | The sources, dates and values behind a finding, both sides shown. | |
| **analyst** | A signed-in user who may see held findings and review them. Everyone else is a non-analyst and never sees a held finding in any form. | |
| **observation** | A community-sourced report about an entity, with consent (`core.observations`, source kind `community`). | "complaint", "tip" |
| **record** | A typed row parsed from a government document (e.g. a water scheme in the register). | "entry" |
| **source** | A document, raster or vector we used, with licence and attribution (`core.sources`). | |
| **provenance** | The chain from a score back to raw bytes: score → `source_ids` → records / EO stats → text blocks → documents → stored bytes (NFR-01). | |
| **Passport** | The entity panel, for every entity type (DEC-20). Not "Parcel Passport" — it names one panel for all four types. | "card", "profile" |
| **retired** | An entity missing from a newer extract: it gets `retired_at` and is never deleted (E15). | "deleted" |

Retired names (DEC-20): **SMI** and **ECI** are not used anywhere. The pitch "Index Stack"
names are replaced by the one name per variable below.

## 2 · Variables — on-screen labels

| ID | Code name | On-screen label | Question it answers |
|---|---|---|---|
| V1 | `V1_activity` | Activity | Is this entity working, and how hard? |
| V2 | `V2_discrepancy` | Record vs reality | Does the record match what we observe? |
| V3 | `V3_momentum` | Momentum | Which direction, how fast, how reliably? |
| V4 | `V4_resource_security` | Resource security | Are the inputs it depends on dependable? |
| V5 | `V5_accessibility` | Access | Can people reach it, dependably, at what cost? |

## 3 · Sub-variables — on-screen labels

Type: **gate** / **guard** / contributor (blank). Labels are short; the tooltip is the question.

| ID | On-screen label | Type | Tooltip |
|---|---|---|---|
| 1.1 | Present | gate | Does it exist where the record says? |
| 1.2 | Working now | | Is it functioning now: yes, no or on and off? |
| 1.3 | How much it is used | | Used ÷ total capacity |
| 1.4 | Uptime | | Time operating ÷ time expected |
| 1.5 | Intensity | | Throughput per unit of capacity |
| 2.1 | Missing from the ground | gate | The record says it exists; the observation says it does not |
| 2.2 | Size gap | | (declared − observed) ÷ declared |
| 2.3 | Type gap | | Declared type, class or use vs observed |
| 2.4 | Status gap | | Declared completion or operation vs observed |
| 2.5 | Record age | | How far the record date lags the latest observation |
| 2.6 | Explanation checked | guard | Is there a legitimate known reason for the gap? |
| 3.1 | Direction | | Is the trend up or down? |
| 3.2 | Rate | | How fast is it changing? |
| 3.3 | Steadiness | | How much does it vary around the trend? |
| 3.4 | Acceleration | | Is the rate itself changing? |
| 3.5 | Neighbourhood trend | | What is changing nearby? |
| 3.6 | Headroom | | How far from saturation? |
| 4.1 | Availability | | How much of the resource is there? |
| 4.2 | Reliability | | How much does supply vary over time? |
| 4.3 | Resource trend | | Is the resource improving or depleting? |
| 4.4 | Competition | | Who else draws on the same resource? |
| 4.5 | Hazard exposure | | How often and how badly is it disrupted? |
| 4.6 | Buffer | | Storage, backup or alternative supply |
| 5.1 | Distance | | Travel time to get there |
| 5.2 | Route condition | | Condition and class of the link |
| 5.3 | Reachable all year | | Share of the year it can be reached |
| 5.4 | Cost to reach | | Transport cost per unit |
| 5.5 | Open and staffed | | Does the service actually operate? |
| 5.6 | Alternatives | | Independent viable routes or sources |

## 4 · Statuses — on-screen labels

| Where | Code value | On-screen label | Hover text |
|---|---|---|---|
| sub-variable | `measured` | Measured | — |
| sub-variable | `null_not_measured` | Not measured | The `null_reason`, verbatim |
| variable | `scored` | (the score) | Coverage and confidence |
| variable | `provisional` | Provisional | "Presence could not be checked — confidence halved." + the gate's `null_reason` |
| variable | `cannot_assess` | Cannot assess | "We checked and it is not there." |
| finding | `detected` | — (internal, never shown) | |
| finding | `held` | Under review (analysts only) | Never shown to a non-analyst in any form |
| finding | `explanation_checked` | Explanation checked (analysts only) | |
| finding | `published` | Published | Both sources with dates |
| finding | `dismissed` | Dismissed (analysts only) | The reviewer's note |
| finding | `contested` | Contested | "Someone has challenged this finding." |
| finding | `resolved` | Resolved | The resolution note |
