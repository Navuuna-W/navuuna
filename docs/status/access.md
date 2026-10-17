# Status — Access lane (one queue)

Anchor: main @ `c934b25` · latest PR seen #138

One file, one queue. Every row below carries an `[api]` or `[web]` tag; the commands take no
argument and read this file (`docs/status/COMMANDS.md`).

## Open PR (one at a time)

| PR | Task IDs | State | Waiting on |
|---|---|---|---|
| this branch | F2 + this status update | opens with this file | `/ship` |

## Merged since last update

Verified with `gh pr view`, not assumed.

| PR | Task IDs | One line |
|---|---|---|
| #138 | A-02a `[api]` | Engine statuses `scored · provisional · cannot_assess`; `p` = provisional by gate, weighted above 0 (C1, S2). Tests now typechecked. |
| #117 | A0 follow-up `[web]` | `detected` added to the finding states; `contested` hidden from viewers (C10, C10b). |
| #116 · #115 | ADR-015 | This one queue, and the no-argument command loop with Access self-merge. |
| #119 · #120 · #121 | Signal — FYI | ADR-016 water register; the source catalogue in `core.sources`. No Access change. |

## In progress

Phase 1 of Austine's plan: land the fresh UI (`docs/design/FRESH-UI.md` §8, F2–F19), then build
G1–G15 below. Each row is one PR off `origin/main`, ≤ 400 changed lines without `schema.d.ts`,
through `/audit` → `/gate` → `/ship` → `/wrap`. Where the backend is not ready the UI reads the dev
mock, and every mock row is marked synthetic. Phase 2 (backend audit, switch to `api`) waits for
Austine's go.

## Next, in order

Phase 1 is `[web]` apart from the contract rows the G screens need first. The `[api]` queue that
was here (A2 rest, A4a, A3, A4b, A5–A11) resumes after Phase 2 in the same order. The old B rows
are superseded: B2–B8 are the F rows, B1c rides in G15.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[web]` | F3 … F19 — one PR per row of `FRESH-UI.md` §8 (F3b is the brand mark) | A-10, A-12 – A-18, A-22, A-24, A-25 |
| 2 | `[web]` | G1 — basemap visible: `npm run basemap` works end to end; a light, calm Protomaps flavour; every score colour ≥ 3:1 on its land; a console warning naming `npm run basemap` when it is missing | A-04, FR-13 |
| 3 | `[web]` | G2 — brand from the logo: Comfortaa (OFL, self-hosted) for display type; brand colours on chrome only; `b1`–`b4` one sequential ramp from the logo teal | design, NFR-02 |
| 4 | `[web]` | G3 — a "Sample data" pill in the header replaces the prototype bar; fixture mode or `synthetic` only | A-08 |
| 5 | `[web]` | G4 — map icons: drop pins with hover and selected states, cased roads with a midpoint badge; pattern, never hue, for `p` and `ca` | A-13 |
| 6 | `[web]` | G5 — polish: glass and depth, data router + view transitions, skeletons, 390/768/1024/1440, reduced motion | A-25 |
| 7 | `[api]` | G6a — `GET /entities` in the draft contract + mock | A-08, FR-16 |
| 8 | `[web]` | G6b — place search and register: type, ward, lens score with coverage and confidence; no finding indicator of any kind; leak test | FR-13, A-19 |
| 9 | `[api]` | G7a — `GET /lenses` in the draft contract + mock | A-15, FR-12 |
| 10 | `[web]` | G7b — lens selector reads `GET /lenses`; never a lens it did not return | A-15 |
| 11 | `[web]` | G8 — ward layer: boundaries and ward entities from the mock tiles, togglable | A-14 |
| 12 | `[web]` | G9 — evidence pack on the finding page + "Same result as an API response" | A-18, FR-10, FR-14 |
| 13 | `[web]` | G10 — live updates: `laravel-echo` + `pusher-js`, Live indicator, pulse + toast, fixture simulator | A-23, FR-15 |
| 14 | `[api]` | G11a — `POST /observations` contract + mock | A-32, FR-17 |
| 15 | `[web]` | G11b — Report what you see (S6): signed in only, consent required | A-32, FR-17 |
| 16 | `[api]` `[web]` | G12 — outcome log on published findings: contract, mock, screen | FR-19 |
| 17 | `[api]` `[web]` | G13 — admin audit view: contract, mock, screen | FR-20 |
| 18 | `[api]` `[web]` | G14 — export GeoJSON / CSV per lens: contract, mock, screen | FR-23 |
| 19 | `[web]` | G15 — final pass: axe at 1440 and 390, a keyboard walk, B1c baselines recaptured | A-25, A-28 |

## Blocked / needs a decision

Nothing blocks the lane. The items below sit in Core paths; each is a one-line request to Khillon
and none stops the work either side of it. They are the standing rulings in
`docs/status/COMMANDS.md`: do the small PR, FYI the owner, never change their logic.

| What | Who |
|---|---|
| A-06 needs `dedoc/scramble`. `composer.json` is a Core path under ADR-008 ("PHP + Composer dependencies"). | Khillon owns the file; Austine has approved the dependency |
| `lens:sync` / `lens:apply` command classes land in `app/Console/Commands/`, a Core path, while the logic stays in Access's `app/Lens`. | Khillon — a named exception, or the commands move |
| Migration M2 — a `colour_class` CHECK on `lens.lens_scores`, with a real `down()`. The column has none today, so a `LensApplier` typo paints silently. Authored by Access in A8. | Khillon approves |
| `GET /me` is not in ADR-008's Access endpoint list, but DEC-13 is Access's and `routes/api.php` is an Access path. Taken as ours and merged as #112. | Khillon to confirm retrospectively |

**PRD-001 is deliberately not in the repo**, by Austine's choice. The sources are merged ADRs,
`docs/BUILD_BIBLE.md`, `docs/CONTEXT.md` and `apps/web/CLAUDE.md`. A claim that would rest on the
PRD is reported as "unverifiable — ask Austine" — never blocking, never guessed. When a task needs
PRD detail, name the excerpt and Austine pastes it.

## Decided without review — Devyan may object

Applied with a defensible recommendation and logged here, per ADR-015. An objection is a follow-up
PR, not a rollback.

- **DEC-20 no longer blocks B3a.** The entity panel is **"Passport"**. Variable names come from
  `docs/CONTEXT.md` verbatim — Activity · Record vs reality · Momentum · Resource security ·
  Access — with CONTEXT.md's question as the tooltip, not as the label. The header reads
  **"Navuuna"**.
  Already true in code: `apps/web/src/copy/labels.ts` holds `PANEL_NAME`, `VARIABLE_LABELS` and
  `VARIABLE_TOOLTIPS`, and `labels.test.ts` pins all three, so a change of mind is a one-file
  change. Devyan owns `docs/CONTEXT.md`; this takes its words rather than inventing any.

## Contract changes between the tracks

Reviewers Khillon + Devyan as FYI.

- **S1 `entity_type` — ruled, not yet applied.** The API serves the storage vocabulary `point ·
  parcel · segment · area` plus `module`, as Bible §6.1 and §11 already define
  (`GET /entities?module=&type=`). The client composes display names in `src/copy/labels.ts`.
  Wards are `entity_type=area`; their module is read from what Devyan's loader writes — **not
  invented here**. Lands with G6a, the first row that serves `entity_type` from `/entities`.
- **S2 `p` suffix — applied in #138.** `p` marks a variable that is provisional because its gate
  was not measured, weighted above 0 in the active lens. A failed gate is `ca`; coverage below 1
  shows through "X of Y" (DEC-15). `tiles.md` §5 moved to match `lenses/README.md`.
- **C1 — applied in #138.** The engine's statuses `scored · provisional · cannot_assess` run from
  the CHECK constraint to the rendered row. The badge still reads "Partly verified" pending README
  open question (b); only its key moved.
- **C10, C10b — applied in #117.** `contested` is analyst-only (ADR-013, `Flag::PUBLIC_STATES`) and
  `detected` is the seventh state.
- **C11 — ruled into A4a**, not A10, so fixture mode and the seeded database never diverge: the
  engine's `coverage` is weight-weighted and the fixtures must weight it the same way.
- **C5, C7, C13, R4, R7 — accepted.** The R4 test pinning Core's `inputs` keys is required, not
  optional.
- **Seeder — option 3 approved.** `generate.ts` → committed `synthetic.json` →
  `SyntheticDemoSeeder` as a plain loader, with a CI diff. Synthetic flag is
  `metadata {"synthetic": true}` plus "(synthetic)" source names; the API's `synthetic` is true when
  **any** contributing source is synthetic. The seeder refuses to run outside local/testing. Rollup
  and lens scores come from running the real commands — never hand-written rows.
- **No `resolved` finding exists in the fixtures**, though it is a public state a viewer will see.
  One line, but it moves the fixture counts, so it belongs with A4a's coverage rework.
- **To Signal:** C7 means findings carry `declared`/`observed`/`gap` + `narrative`, not pre-composed
  sentences. The gap wording stays in `docs/CONTEXT.md`; this lane will not author it.
- **To Signal:** C13 drops `name`/`label` from the response — labels stay in `apps/web/src/copy`,
  so a DEC-20 change is a one-file change.
- **To Core:** G12–G14 describe Core's outcome log, audit log and export (FR-19, FR-20, FR-23) in
  the draft contract so their screens can be built against a mock. The shapes are Khillon's to
  change — a correction is a follow-up PR against the yaml.

## Design: remaining differences from the reference

The eleven deliberate deviations and their reasoning live in `docs/design/DESIGN-mapping.md` §2:
red/amber/green status encoding dropped; no bare scores; no dash for missing data; "asset" banned;
no cause-or-intent copy; held findings invisible; fonts actually loaded; real hover and pressed
states; four light-theme contrast failures fixed; proper tab semantics and roving tabindex; URL
state. `FRESH-UI.md` departs from `DESIGN.md` on radius, shadow and motion only.

Decided, kept here so they are not re-litigated:

- **Entity register:** G6 builds it. Leak rule: it reads `GET /entities`, which never carries
  finding state, and shows a viewer no finding indicator of any kind.
- **Dark theme:** recorded, not built. Thirty hex values in `DESIGN.md` §1.2; light theme only.
- **Raw-response block:** G9 renders the exact JSON the client fetched — no client-side composition.
- **Source chips** yes; the five-dot confidence glyph no.
- **`--line`, `--water`, `--new` and the notice colour** carry no status meaning. Text ≥ 4.5:1,
  UI ≥ 3:1. Red is reserved for system errors — never a score, never a finding.

## Unmerged local branches

This repo squash-merges, so `git branch --no-merged` lists merged work too. These still carry
content that is not on `main`:

| Branch | Head | What it is |
|---|---|---|
| `feat/web-passport-rows` | `a427c6c` | The F2–F19 chain, 38 commits on `dbfaa39`. Every F row is cut from it; the per-row local branches point into it and are deleted as their rows merge. |
| `feat/web-fresh-ui` | `4afe74b` | The reference implementation. A source to copy from, never merged. |
| `test/web-visual-regression-harness` | `3078c36` | B1c — harness page, token-name test, four baselines. Lands in G15. |
| `docs/contract-detected-state-and-tiles-fixes` | `a3741d5` | The old A2 partial. #117 and #138 landed most of it; check before deleting. |
| `fix/web-scale-fallback-and-line-styles` | `e164bba` | **Unverified.** Looks superseded by #66 and #73. Check before deleting. |

## Gate

- #138 — `gh pr view 138` → MERGED as `c934b25`; all 9 checks green on its head
- `gh pr view` on #115, #116, #117, #119, #120, #121 → all MERGED
