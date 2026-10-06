# ADR-010 — Findings rules: thresholds, held V2, review transitions, "X of Y"

- **Number:** 010
- **Date:** 6 Oct 2026
- **Status:** Proposed (accepted on merge)
- **Decider:** Devyan (Signal — owns DEC-08, DEC-09, DEC-10, DEC-11)
- **Refs:** D-11, FR-10, FR-11, Bible §6.3, §6.5, E8

## Context

The findings engine (K-13) and review workflow (K-14, A-22) need four decisions the Bible
leaves open. Each is written below as the rule to build. Sub-variable scores here are the
direction-neutral 0–100 scores from `docs/modules/water.md`, where higher = bigger gap.

## Decision 1 — DEC-09: when a finding is raised, and how severe it is

A finding is raised for an entity × sub-variable when **all** of these hold:

1. The entity's V1 gate (1.1) is measured. **Never for a provisional entity** (Bible §6.3).
2. The sub-variable is `measured`.
3. Its confidence ≥ `MIN_FINDING_CONFIDENCE = 0.5`.
4. Its score ≥ the raise threshold below.
5. No open finding already exists for the same entity × sub-variable.

| Sub-variable | Raise at score ≥ | Severity: low | medium | high |
|---|---|---|---|---|
| 2.1 Existence gap | 100 (gap) | — | — | always **high** |
| 2.2 Magnitude gap | 30 | 30–49 | 50–74 | 75–100 |
| 2.4 Status gap | 50 | 50 (intermittent) | — | 100 (not working) |
| 2.5 Record staleness | — | never raised alone | | |

2.5 never raises a finding by itself: an old record is not a claim about anyone. It lowers
confidence on the other gaps (water spec, rule 8) and is shown in the evidence pack.

**E8 — auto-resolve.** A **published** finding moves to `resolved` when two consecutive runs,
at least `MIN_DAYS_BETWEEN_RESOLVING_RUNS = 7` days apart, score the sub-variable below
`AUTO_RESOLVE_BELOW = raise threshold − 20` (hysteresis, so a value hovering at the threshold
does not flip). The audit event's actor is `system`, with both scores in the note. A held
finding whose gap closes is dismissed the same way, never published.

## Decision 2 — DEC-08: V2 while a finding is held

A gap under review must not reach a non-analyst through **any** route — score, lens colour,
tile, count.

- The rollup **excludes** every 2.x sub-variable that has a `held` or `explanation_checked`
  finding from the stored V2 row (it is treated as not available, so coverage drops honestly).
- When the finding is published or dismissed, the entity's V2 is re-rolled and the lens
  re-applied (`EntityScored` event).
- Analysts see the held sub-variable in the review screen only, never in the public Passport.

## Decision 3 — DEC-10: the PRD's three buttons vs the Bible lifecycle

The PRD has Publish / Dismiss / Needs more evidence. The Bible has
`held → explanation_checked → published | dismissed`. Both are kept:

| Button | Transitions recorded (one request) | Required |
|---|---|---|
| **Publish** | `held → explanation_checked` (with the explanation answer "none found") **then** `explanation_checked → published` | Note. Both events carry the same note, user and time. |
| **Dismiss** | `held → explanation_checked` (with the legitimate explanation chosen) **then** `explanation_checked → dismissed` | Note + one explanation from the candidate list (D-20) or free text. |
| **Needs more evidence** | No state change. Stays `held`. Audit event `note_added`. | Note. |

The 2.6 guard is satisfied by the `explanation_checked` step, which every published or
dismissed finding passes through. No finding can skip it.

## Decision 4 — DEC-11: what "X of Y" means

`X of Y` on a variable = measured contributors of all weighted contributors.
Y comes from `weights.yml`: **V1 4 · V2 4 · V3 6 · V4 6 · V5 6**. Gates (1.1, 2.1) and the
guard (2.6) are never counted. The PRD mock's "4 of 6" on Activity is wrong and becomes
"_ of 4".

## Consequences

- K-13 can be built from Decision 1 alone; thresholds live as named constants beside it.
- A finding never reaches a viewer before a person has checked for an explanation.
- Changing a threshold changes what gets raised, not any score: it needs a new ADR, not a
  `weight_version` bump.
