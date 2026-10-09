# Design — mapping, deviations, and what we are not building

What this file is: the decision half of the design capture. [`DESIGN.md`](DESIGN.md) holds the
measured numbers; this holds the judgements — which parts of the look-and-feel reference at
<https://jmursi.github.io/navuuna/> we adopt, which we change, where our rules override it
outright, and what we are deliberately not building.

**The reference is a prototype for a different product** — agricultural lending in Kenya, scoring
farms for lenders and insurers. We take its visual language. We do not take its domain, its
vocabulary or its colour semantics.

Tagged **design**, not an A-task.

A note on names: `A-10` with a hyphen is a work-pack task ID (sign-in, S7). `A10` without one is
the tenth PR in the approved Track A order. They are different things.

---

## 1 · Mapping — what we take, what we change

Each line is CONFIRM (adopt as proposed) or CHALLENGE (the proposal needs changing), followed by
the reference features the proposal did not cover. **Six CONFIRM, three CHALLENGE, five
additions.** All nine open questions in here are now settled; the ruling is recorded on each.

### CONFIRM

1. **"Who is reading the result" → our lens selector, county planner only.** CONFIRM.
   The pattern is right and it is already A-15. The reference's three readers (Lender / Insurer /
   Programme funder) are its own domain and must not be copied; we render the one lens we have. A
   segmented control with a single option looks broken, so render it as a labelled static value
   until a second lens exists, and keep the segmented component ready behind it.

2. **"Map layer" group → layer / module / entity-type filters.** CONFIRM.
   One caution: the reference's four layers are _mutually exclusive_ (`aria-pressed`, one-of-N).
   Our module and entity-type filters are _multi-select_. Same visual family, different component
   — segmented control for one-of-N, toggle group for many-of-N, and they must not look identical.

3. **"Synthetic data…" notice → our banner, our wording.** CONFIRM.
   The reference's is a small amber pill in the header corner, dismissible by scrolling past.
   Ours is non-dismissable and spans the top. We take the idea, not the treatment, and not the
   amber — that exact hex fails AA (`DESIGN.md` §1.7).

4. **"Evidence and limits" → methodology page, empty layout, content from Devyan.** CONFIRM.
   The reference's four-column claim / verdict / evidence / action table is a good skeleton.
   Build the layout and leave the rows empty.

5. **Confidence band chart → not built.** CONFIRM, emphatically.
   The band is `(1 − confidence) × 0.25` — an arbitrary constant, not a derived interval. Our
   confidence is a 0–1 rollup factor with no variance attached, so there is no honest width to
   draw. Drawing one would invent precision. Revisit only if the engine ever publishes an actual
   interval.

6. **Portfolio triage, Asset graph, Learning loop, ground-truth visit, Play → not built; never
   render a tab that leads nowhere.** CONFIRM. All of them lean on data we do not have — a
   comparison cohort, an entity-resolution graph, outcome labels, a ground-truth network. See §3.

### CHALLENGE

7. **"Verify an asset" tab → Map + Passport, labelled "Verify a place".** CHALLENGE — the rename
   is right, the label is not. "Verify a place" is a verb phrase for a task, but this is the
   product's default view, not an action the user chose. And "verify" is a loaded word: we do not
   verify, we _report a gap between a record and an observation_, and we are explicit that we may
   be wrong.
   **Settled:** the tab is **"Map"** and the panel heading is **"Passport"** (`docs/CONTEXT.md`
   already uses Passport). "Verify" stays out of the UI entirely. Remaining on-screen wording is
   still DEC-20.

8. **"Resolution slider" → not built.** CHALLENGE on a factual point: it is **not a slider**.
   `#resSeg` is a three-option segmented control (10 m free / 3 m subscription / 0.5 m tasked).
   The only real sliders in the reference are the season `#tSlider` and the Learning-loop
   `#months`. The decision not to build it stands — we have no imagery tier to sell — but the
   plan says "resolution segmented control" so nobody goes looking for a slider.

9. **Register / "Sample assets" rail → unmapped in the proposal.** CHALLENGE: it needed a
   decision. The reference's left rail is a short list of pickable entities with a status dot
   each. A-12 specifies our left rail as lens + filters + legend, with entity selection happening
   on the map. Those compete for the same 230 px.
   **Settled: not built this round.** The A-12 rail is lens + filters + legend only. Logged in §3
   with the leak rule that governs any future entity list.

### Additions — reference features the proposal did not cover

10. **Dark theme.** A complete second palette behind `prefers-color-scheme`, in no A-task.
    **Settled: recorded, not built.** The tokens are written down in `DESIGN.md` §1.2 so shipping
    dark later is a token exercise; this round is light theme only.

11. **"The same result as an API response" `<details>`.** A collapsible raw-JSON view of the
    current entity. Cheap, and it makes the "we are a queryable layer, not a dashboard" claim
    concrete.
    **Settled: adopt, backlog, after Track A's A10.** It must render **the exact JSON the client
    fetched** — no client-side composition, or it stops being evidence of anything. That means it
    cannot be built before the response it displays exists. It runs through the same role filter
    as everything else, or it becomes a held-finding leak.

12. **Confidence "what would improve this" tip.** The reference says "A ground visit would lift
    this to 71 %. 3 m imagery would give 84 %."
    **Settled: adopt the pattern in B4, for not-measured rows only, built from `null_reason`.**
    The reason field already says why a sub-variable could not be measured, so the tip is a
    restatement of our own data. **No invented advice** — if `null_reason` does not say it, we do
    not say it. Strong fit with the never-show-a-bare-0 rule.

13. **Source chips + per-variable confidence dots on every row.** `S2`, `S1`, `GIS`, `Admin`,
    `Ground` chips, and a 5-dot confidence glyph per variable.
    **Settled: chips yes, dots no.** We already carry `sources[{name, kind, date}]`, so the chips
    are free and they make provenance visible. The 5-dot glyph re-quantises a continuous
    confidence into five buckets, and we already show a bar and a word beside every score.

14. **Grouped variable rows.** The reference groups 13 variables under four plain-language
    headings ("Is it real and in use", "How it is changing", "Does it match the record", "What
    surrounds it"). Our five variables already have plain names, so we do not need the grouping —
    but that _register of phrasing_ is the right one and should inform DEC-20.

---

## 2 · Deviations — where our rules override the reference

Eleven places. Our rules win in all of them.

### 2.1 Red/amber/green status encoding — the big one

The reference encodes every verdict on a traffic light: `.ok → --obs` green, `.verify → --warn`
amber, `.escalate → --bad` red, `.insufficient → --muted` grey. That single `--c` variable then
drives the verdict block's left border and tint, the confidence meter fill, the register status
dot, the KPI border and the evidence-table pill.

This breaks our rules outright. **We drop `--warn` and `--bad` as status colours entirely** and
paint bands from one sequential colour-blind-safe ramp (`b1`–`b4` per `docs/contracts/tiles.md`
§5), with `p` carried by a hatch and `ca` by a neutral pattern — so pattern, not hue, carries the
critical distinction. The ramp's constraints and its verification script are in `DESIGN.md` §1.3.
Red survives only for system errors — failed loads, form validation — never for a score or a
finding.

Knock-on: the reference's `color-mix(in srgb, var(--c) 10%, var(--panel))` verdict tint and the
`--c`-driven meter both need re-specifying against the sequential ramp.

### 2.2 Scores shown without coverage or confidence

`.v .val` renders a bare value — "High", "26 %", "Stable" — with only a 5-dot glyph for confidence
and no coverage at all. NFR-02 forbids this. Every one of our variable rows carries score +
coverage bar + "X of Y" + confidence bar + word, and the resource throws rather than emit a bare
score.

### 2.3 A dash for missing data

`varRows` emits `'-'+pct(…)` and `'Not observed'` inline with real values, with nothing to mark
them as unmeasured. Our rule: `null_not_measured` renders as "Not measured" plus its reason,
never `0`, never an em dash, never mixed in as if it were a measurement.

### 2.4 "Asset" everywhere

The reference says "asset" 20+ times — tab name, rail heading ("Sample assets"),
`aria-label="Asset register"`, the API payload key `asset_id`, a whole tab called "Asset graph".
Banned for a scored entity. Ours are places: water point, road segment, ward — "entity" in code
and "place" in copy, per `docs/CONTEXT.md`. This also rules out the proposed "Verify an asset" tab
name (§1.7).

### 2.5 Verdict copy attributes cause and intent

"Treat as possible under-use or abandonment", "Escalate", "possible under-use". Our copy rule:
describe the gap, never a cause, an intent or a person. Ours reads "The register records this as
operational; we observe no activity since <date>" and stops there.

### 2.6 Everything is visible to everyone

There is no role model at all — no sign-in, no held state, no review queue. Every judgement is on
screen for any visitor. Our held findings are invisible to viewers in every form: no row, count,
badge, placeholder or colour. The reference offers no pattern to copy here, so the review screens
(A-22) are ours to design from the PRD.

### 2.7 Fonts named but never loaded

No `@font-face`, no font link — so Public Sans and Bricolage Grotesque silently fall back to Segoe
UI. Both named families are SIL OFL 1.1, so we self-host both as woff2 Latin subsets with
`font-display: swap` and a matched fallback metric (`DESIGN.md` §1.4). Zero third-party runtime
requests is already true of the reference by accident; for us it is a rule.

### 2.8 No hover or `:active` affordance

Verified by byte-identical screenshots: segmented buttons, tabs and the Play button look exactly
the same at rest, on hover and with the mouse held down. The only hover rule in the entire
stylesheet is `.reg:hover`. Every interactive element we build gets a visible hover and a visible
pressed state in addition to the focus ring.

### 2.9 Contrast failures against AA

Four light-theme pairs fail, measured from the real hex values; the table is in `DESIGN.md` §1.7.
The worst of them is the amber on the synthetic-data notice — the one piece of text on the page
that most needs to be read. Actions for the tokens PR: darken `--line` to clear 3:1 against both
surfaces, or accept it as decorative only and never let a border be the sole carrier of a
boundary; re-pick `--water` and `--new` as data colours that clear 3:1; drop `--warn`/`--bad`
anyway per §2.1.

### 2.10 Keyboard and semantics gaps

`role="tablist"` with `role="tab"` buttons, but no `aria-controls`, no `tabpanel` roles, and no
arrow-key roving tabindex — so the tabs announce as tabs but do not behave as tabs. The segmented
groups have no `role="group"` and no group label tied to the `.ctl>span`. Ours need
`aria-controls` / `role="tabpanel"`, arrow-key navigation, and a real accessible name on every
group.

### 2.11 No URL state

Nothing is shareable: all state lives in one JS object (`DESIGN.md` §4). A-14 requires lens,
filters, centre, zoom and the selected entity in the URL.

---

## 3 · Not built — the register, and why

Recorded so nobody re-opens these by accident, and so no tab is ever rendered that leads nowhere.

| What | Why not | What would unblock it |
|---|---|---|
| Confidence band chart | `(1 − confidence) × 0.25` is an arbitrary constant, not an interval. Drawing it invents precision | The engine publishing an actual variance |
| Portfolio triage | Needs a comparison cohort we do not have | Devyan |
| Asset graph | Needs an entity-resolution graph | Devyan |
| Learning loop | Needs outcome labels | Devyan |
| Ground-truth visit toggle | Needs a ground-truth network | Devyan |
| Play / season stepping | Needs a time series per entity | Devyan |
| Resolution control | No imagery tier to sell. Note it is a segmented control, not a slider (§1.8) | A commercial decision, not a design one |
| Dark theme | Tokens recorded in `DESIGN.md` §1.2; in no A-task | Austine scheduling it |
| Entity register rail | A-12 gives the 230 px rail to lens + filters + legend. Our entity count is far past a six-row list, and a list of entities with status dots is exactly the shape that leaks held findings | Austine, under the leak rule below |
| Raw-response `<details>` | Must show the exact JSON the client fetched, which does not exist yet | Track A's A10 |

**The leak rule, for any future entity list.** If an entity rail is ever built, it reads
`GET /entities` — which never carries finding state — and it shows a viewer **no finding indicator
of any kind**: no status dot, no count, no badge, no placeholder, no colour. The reference's rail
is a list of entities each wearing its verdict as a coloured dot; that is the precise shape our
held-finding rule forbids. A viewer's rail may show identity and score only, with coverage and
confidence beside the score as everywhere else.
