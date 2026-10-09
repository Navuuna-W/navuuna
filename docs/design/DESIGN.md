# Design — tokens and layout

What this file is: the measured visual system of the look-and-feel reference at
<https://jmursi.github.io/navuuna/>, reduced to the numbers `apps/web` builds from. It exists so
the tokens PR has exact values instead of eyeballed screenshots.

Every value below was read out of the reference's stylesheet, not sampled from an image. The
mirrored copy lives in `docs/design/reference/` (gitignored — we copy the look, never the files).
Screenshots are in `docs/design/reference/shots/`, also gitignored: 86 captures at 1440×900 and
390×844, covering every tab, the rest/hover/active/focus states of the segmented controls, layer
toggles, slider and Play, and the chart at low and high confidence.

**The reference is a prototype for a different product** — agricultural lending in Kenya, scoring
farms for lenders and insurers. We take its visual language. We do not take its domain, its
vocabulary or its colour semantics. What we adopt, change and reject is in
[`DESIGN-mapping.md`](DESIGN-mapping.md), including every place our rules override it.

Tagged **design**, not an A-task.

---

## 1 · Tokens

The reference declares its whole palette as CSS custom properties on `:root`, with a full dark set
behind both `prefers-color-scheme: dark` and an explicit `[data-theme="dark"]`. Fifteen colour
tokens per theme, thirty in total.

### 1.1 Colour — light

| Token | Hex | Role in the reference | Our role |
|---|---|---|---|
| `--bg` | `#e6ece8` | Page background | Surface, page |
| `--panel` | `#f5f8f4` | Card / panel / header fill | Surface, raised |
| `--ink` | `#13242a` | Primary text, strong borders, selected fill | Text, primary |
| `--muted` | `#546a69` | Secondary text, labels, axis text | Text, secondary |
| `--line` | `#c3cfc9` | Hairline borders, grid lines | Border, subtle — **re-pick, fails 3:1** |
| `--chip` | `#dde6e0` | Chip fill, meter track, hover fill | Surface, sunken |
| `--mapbg` | `#f2f4ea` | Map canvas background | Map surface — the ramp is measured against this |
| `--obs` | `#2b7a4b` | "Observed" green — data **and** "good" status | **Data only. Never status.** Candidate ramp hue |
| `--obs-soft` | `#b4dbc0` | Light green fill | Data, low end |
| `--decl` | `#3247a0` | "Declared" blue — data, links, focus ring | Accent / focus / link |
| `--warn` | `#b3771a` | Amber — "verify" status | **Dropped as status.** Replacement carries no status meaning |
| `--bad` | `#b0304a` | Red — "escalate" status | **Dropped as status.** Red is for system errors only |
| `--water` | `#5d9dc6` | River / flood layer | Data, water — **re-pick, fails 3:1** |
| `--new` | `#178f8f` | Teal — newly-cultivated | Data, change — **re-pick, fails 4.5:1** |
| `--hatch` | `#c9c7b4` | Hatch pattern stroke, "not cultivated" | Pattern — carries `p` (partly verified) |
| `--shadow` | `none` | — | **No shadows anywhere.** Depth is borders only |

### 1.2 Colour — dark

| Token | Hex | | Token | Hex |
|---|---|---|---|---|
| `--bg` | `#0f1a1c` | | `--obs` | `#5cc088` |
| `--panel` | `#152326` | | `--obs-soft` | `#2a5a41` |
| `--ink` | `#e4eeea` | | `--decl` | `#8fa2ff` |
| `--muted` | `#95aba6` | | `--warn` | `#e0a84a` |
| `--line` | `#294146` | | `--bad` | `#f07a94` |
| `--chip` | `#1d3236` | | `--water` | `#4f8db5` |
| `--mapbg` | `#132125` | | `--new` | `#3cc7c7` |
| `--hatch` | `#34484b` | | | |

Dark mode is a genuine second palette, not a filter. **Recorded, not built: light theme only
this round** (ruling 16). It is in no A-task. The table is kept so that shipping dark later is a
token exercise rather than an archaeology exercise.

### 1.3 The score ramp — ours to design, not the reference's

The reference has **no sequential scale to copy**: every verdict rides a traffic light, which
breaks our rule 5 outright (see `DESIGN-mapping.md` §2.1). So `b1`–`b4` is ours to pick, under
these constraints:

- **One single-hue sequential ramp**, derived from the reference's primary accent (`--obs`
  `#2b7a4b` is the candidate hue). Four steps, bands per `docs/contracts/tiles.md` §5:
  `b1` 80–100 · `b2` 60–79 · `b3` 40–59 · `b4` 0–39.
- **`p` (partly verified) is carried by a hatch**, `ca` (cannot assess) by a **neutral pattern**.
  Pattern, not hue, carries the critical distinction — colour is never the only carrier of
  meaning.
- **Verified by script, not by eye.** Adjacent steps must stay distinguishable under deuteranopia
  **and** protanopia simulation, and each step must clear **≥ 3:1 against the basemap land
  colour** (`--mapbg` `#f2f4ea`).
- Swatches and measured numbers go in the tokens PR for Austine's approval **before** it merges.

`--warn` and `--bad` replacements follow the same discipline: they **carry no status meaning**,
text clears **≥ 4.5:1** and UI elements **≥ 3:1** against their backgrounds. **Red is reserved
for system errors** — failed loads, form validation — and is never used for a score or a finding.

### 1.4 Type

Two families, both named but **never actually loaded** — the reference ships no `@font-face` and
no font CDN link, so on most machines it silently renders in Segoe UI. Both named families are
SIL OFL 1.1, so we **self-host both** as woff2 Latin subsets with a `system-ui` fallback and
`font-display: swap`. Not Segoe: it is Windows-only and not redistributable.

| Role | Stack as declared | Real first choice | Licence |
|---|---|---|---|
| Body, UI, SVG text | `"Public Sans", "Segoe UI", system-ui, -apple-system, Roboto, Helvetica, Arial, sans-serif` | Public Sans | SIL OFL 1.1 (US GSA) — self-hostable |
| `h1 h2 h3`, big numerals, brand | `"Bricolage Grotesque", "Trebuchet MS", "Segoe UI", system-ui, sans-serif` | Bricolage Grotesque | SIL OFL 1.1, variable font — self-hostable |

Headings carry `letter-spacing: -.01em` and `margin: 0`.

**Type scale** — fourteen distinct sizes, all px, no modular ratio. Base is `15px/1.5`.

| px | Where |
|---|---|
| 30 | `.conf .big b` — the headline confidence number (Bricolage 700) |
| 26 | `.kpi b` — KPI numeral (Bricolage 700) |
| 20 | `.brand`, `.rhead h2` |
| 17 | `.verdict h3`, `.panel h3` |
| 15 | body base, `.chart h3` |
| 14 | `.seg button`, `.check`, `.reg b`, `.v .n`, `table` |
| 13.5 | `.rhead p` |
| 13 | `.ctl>span`, `.legend`, `.chart p`, `.factors`, `.tip`, `.vgroup`, `th`, `details`, `.note`, `.brand small` |
| 12.5 | `.reg span`, `.v .d`, `.flag` |
| 12 | `.pill`, `details pre`, SVG labels |
| 11.5 | `.chip`, `.dots`, SVG minor labels |
| 11 | chart axis text |

Line-height is `1.5` globally and never overridden. Weights used: 400 (body), 600 (`nav`
selected, `th`, `.reg` headings, `.v .val`, `.pill`, `.time output`), 700 (`.brand`,
`.conf .big b`, `.kpi b`).

### 1.5 Spacing

No scale token — raw px, but it clusters on an implicit 2px grid with a strong preference for
**2 · 4 · 6 · 8 · 10 · 12 · 14 · 16 · 18 · 20 · 22 · 26 · 28 · 40**.

Recurring pairs: `padding: 14px 22px` (header), `18px 22px 40px` (main), `14px 16px` (panel /
verdict / report head), `10px 12px` (table cell), `8px 10px` (register row, list button),
`6px 11px` (segmented button), `3px 9px` (flag pill), `1px 8px` (pill). Gaps: `2px` (register
rows), `4px`, `6px`, `8px`, `10px`, `12px`, `14px 26px` (controls row), `12px 28px` (header),
`18px` (main grid), `20px` (two-column).

### 1.6 Radii, borders, shadow, motion

- **Radii:** `0` is the default and dominant — panels, cards, tables, the map sheet, meters and
  chips-with-background all have square corners. Only four exceptions: `6px` on `.seg`,
  `.time button`, `.qlist button`; `3px` on `.chip`; `99px` on `.flag` and `.pill`; `50%` on
  `.dot`.
- **Borders:** `1px solid var(--line)` is the standard hairline. `1px solid var(--ink)` is the
  emphatic one (`.sheet`, `.sw`). Accent bars: `border-left: 3px` (`.reg`, `.v`), `4px` (`.kpi`),
  `6px` (`.verdict`); `border-bottom: 2px` (selected nav tab).
- **Shadow:** `--shadow: none`. There is not one `box-shadow` in the stylesheet. Elevation is
  communicated entirely by a 1px border and a panel fill. **Adopt this.**
- **Motion:** no `transition` and no `@keyframes` anywhere. The only motion is the Play control
  stepping the season on a `setInterval` of 650 ms. There is a `prefers-reduced-motion: reduce`
  block that kills transitions and animations globally — defensive, since there are none. If we
  add transitions we must honour that block.

### 1.7 Measured contrast — what must be re-picked

Measured from the real hex values. The light theme fails four pairs:

| Pair | Ratio | Needs | Where it bites |
|---|---|---|---|
| `--warn #b3771a` on `--panel #f5f8f4` | **3.52** | 4.5 | The "Synthetic data…" header notice, and every "Verify" pill |
| `--new #178f8f` on `--panel #f5f8f4` | **3.66** | 4.5 | "Newly cultivated" legend text |
| `--line #c3cfc9` on `--panel #f5f8f4` | **1.50** | 3.0 | Every 1px border and the whole map grid |
| `--water #5d9dc6` on `--panel #f5f8f4` | **2.76** | 3.0 | River stroke, flood layer |

Dark theme is much better — only `--line #294146` on `--panel #152326` at **1.49** fails.

Passing but close, worth watching: `--muted` on `--chip` at **4.52**, `--muted` on `--bg` at
**4.81**, `--obs` on `--panel` at **4.92**, `--obs` on `--bg` at **4.40** (UI, needs 3.0).

The failing amber is exactly the colour the reference uses for its synthetic-data notice — the
one piece of text on the page that most needs to be read. Our banner must clear AA.

---

## 2 · Layout

- **Page container:** `main { max-width: 1500px; margin: 0 auto; padding: 18px 22px 40px }`.
- **Header:** flex, wrapping, `gap: 12px 28px`, `padding: 14px 22px`, `--panel` fill, 1px bottom
  border. Brand left, tab list centre, synthetic-data pill pushed right by `margin-left: auto`.
- **Main grid:** `grid-template-columns: 230px minmax(0,1fr) 370px; gap: 18px;
  align-items: start`. Fixed-width rails either side of a fluid middle.
- **Secondary grid** (`.two`): `minmax(0,1fr) 340px; gap: 20px`.
- **KPI row:** `repeat(4, 1fr); gap: 10px`.
- **Breakpoints:** only two, both `max-width`. `1180px` collapses the main grid to one column;
  `1050px` collapses `.two`. There is **no** small/mobile breakpoint — at 390px the page is the
  1-column layout with the header wrapping, and nothing is re-designed for small screens. Ours
  is a separate piece of work (390 px PR).
- **Known defect, do not copy:** at `≤1180px` the register is given
  `display:flex; overflow-x:auto` and its rows `min-width:190px`, clearly intending a horizontal
  scroller — but the base rule's `flex-direction: column` is never reset, so it stays a vertical
  list and `overflow-x` does nothing. Visible in `shots/390x844--tab-verify.png`.

---

## 3 · Component inventory

| Component | Anatomy | States | Where |
|---|---|---|---|
| **Tabs** (`nav[role=tablist]`) | `button[role=tab][data-tab]`, 8×10 padding, 2px transparent bottom border | rest (muted) · selected (`aria-selected` → ink text, `--obs` bottom border, 600) · focus-visible. **No hover state** | Header |
| **Segmented control** (`.seg`) | 1px `--line` wrapper, `6px` radius, `overflow:hidden`, children divided by 1px right borders | rest · pressed (`aria-pressed` → `--ink` fill, `--bg` text) · focus-visible. **No hover, no `:active`** — verified byte-identical shots | Lens, map layer, resolution |
| **Checkbox row** (`.check`) | native `input[type=checkbox]` + label, `gap:8px`, 14px | rest · checked · focus-visible | Ground-truth visit, record mismatch |
| **Register row** (`.reg`) | full-width button, `3px` transparent left border, `<b>` with status `.dot` + `<span>` subtitle | rest · **hover (`--chip` fill) — the only hover rule in the file** · current (`aria-current` → ink left border, panel fill) | Left rail |
| **Status dot** (`.dot`) | 9×9 circle, `--c` from a status class | one per status class | Register |
| **Button** (`.time button`, `.qlist button`) | 1px `--line`, `6px` radius, transparent fill, `4px 10px` / `8px 10px` | rest · pressed · focus-visible. No hover | Play, portfolio questions |
| **Pill / badge** (`.pill`, `.flag`) | `99px` radius, 1px border in `--c`, text in `--c`, 12px/600 | one per status colour | Evidence table, header notice |
| **Chip** (`.chip`) | `--chip` fill, `3px` radius, `0 6px`, 11.5px muted | static | Source tags on variable rows |
| **Card / panel** (`.panel`, `.report`, `.tbl`) | `--panel` fill, 1px `--line`, square, `14px 16px` | static | Everywhere |
| **Map sheet** (`.sheet`) | `--panel` fill, **1px `--ink`** border (heavier than a panel), `10px` padding, SVG `width:100%;height:auto` | static | Main view |
| **Notice** (`.flag`) | amber text + amber 1px border, pill, 12.5px | static | Header — "Synthetic data…" |
| **Verdict block** (`.verdict`) | `6px` left border in `--c`, fill `color-mix(in srgb, var(--c) 10%, var(--panel))`, tag + h3 + paragraph | four status variants | Report head |
| **Meter** (`.meter`) | 7px track in `--chip`, `<i>` fill in `--c`, square, no radius | width = % | Confidence |
| **Variable row** (`.v`) | 2-col grid (name / value), `3px` transparent left border, optional `.d` description row and `.chips` row spanning both | rest · key (`--decl` left border + 7% decl tint) | Report |
| **Tooltip** | native `title=""` only — no custom tooltip component exists | — | Confidence dots |
| **Slider** | native `input[type=range]`, `accent-color: var(--obs)`, `flex:1` | native hover / active / focus-visible | Season, months |
| **Chart** (`.chart`) | top 1px `--line` rule, h3 + muted p + inline SVG; gridlines, dashed `--decl` reference line, `--obs` polyline at 2.6px, 20%-opacity band polygon, 3px dots with the selected point at 5.5px ink | band half-width = `(1 − confidence) × 0.25` | Main view |
| **Table** (`table`) | `border-collapse:collapse`, 1px bottom rules, `th` muted 13px/600 | row hover: none | Evidence |
| **Details/pre** (`details pre`) | `--chip` fill, 10px, 12px mono-ish, `max-height:280px`, scroll | open / closed | "Same result as an API response" |

**Focus** is a single global rule: `:focus-visible { outline: 2px solid var(--decl);
outline-offset: 2px }`. It is the one piece of interaction styling the reference does consistently
well — adopt it verbatim, with our own accent.

---

## 4 · Interaction patterns

- **State lives in one object** (`state = {farm, lens, layer, res, gt, t, playing}`) and every
  control mutates it then calls a full re-render. Nothing is in the URL — a view cannot be shared
  or bookmarked. **We do the opposite:** lens, filters, centre, zoom and selected entity all go
  in the URL.
- **Selection is expressed with ARIA, not classes:** `aria-pressed` on segmented buttons,
  `aria-selected` on tabs, `aria-current` on register rows. Good — copy this.
- **The reader-type control re-frames the whole result.** Switching reader changes the verdict
  text, which variable rows are highlighted and the recommendation — but **not** the underlying
  measurements. That separation is exactly our lens-invariance rule, and it is the single best
  idea in the reference.
- **Cost is made visible.** The resolution control is labelled "cost rises to the right" and the
  confidence panel tells you what buying more would get you. An honest pattern, though we have
  nothing to sell.
- **The confidence band is the headline** — "the honest width of the answer". Shown at 37 % vs
  96 % in `shots/*--chart-confidence-low.png` / `-high.png`. We do not build it; see
  `DESIGN-mapping.md` §1.
- **No transitions.** Every state change is an instant repaint.
- **Hover is almost absent** — one rule in the entire stylesheet (`.reg:hover`). Segmented
  buttons, tabs and Play are byte-identically unchanged on hover and on `:active`. This is an
  affordance gap, not a style to copy.

---

## 5 · What the tokens PR starts from

**Ready:** the full light palette minus `--warn`/`--bad`; the 14-step px type scale; the spacing
clusters; radii (`0` default, `6`/`3`/`99`/`50%` exceptions); the 1px border system;
`--shadow: none`; the global focus ring; both font families as self-hosted OFL woff2 subsets.

**Decided, needs the numbers produced and shown before merge** (§1.3): the `b1`–`b4` single-hue
ramp with its deuteranopia/protanopia and ≥ 3:1-against-`--mapbg` script output; replacement
hexes for `--line`, `--water`, `--new` and the notice colour, all carrying no status meaning.

**Still with Devyan:** on-screen vocabulary beyond the two settled labels — the tab is **"Map"**
and the panel heading is **"Passport"** (`docs/CONTEXT.md` already uses Passport). Remaining
wording is DEC-20.
