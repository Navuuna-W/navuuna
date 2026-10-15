# Status — Access lane (one queue)

Anchor: main @ `dbfaa39` · latest PR seen #121 (Devyan's, merged)

## Standing rules

1. **A status update never gets its own PR.** `/wrap` commits this file onto the next queue item's
   branch, cut from `origin/main`, and `/ship` carries it there. A status-only PR is closed, not
   iterated on — #118 was closed unmerged under this rule and its content is here.
2. **Keep this file short.** No commentary on pinned shas or runs; live values come from `gh`.
3. **The place register is allowed**, with entity data only, no finding indicator of any kind, and
   `PlaceRegister.leak.test.tsx` required. Settled, not an open question.
4. **The demo is done, so `feat/web-demo` is now a salvage source and nothing else.** It is never
   merged whole — it predates #117. Every S row below cuts a fresh branch from `origin/main`,
   cherry-picks or reapplies one slice at ≤ 400 changed lines, and keeps #117's guards: the
   `detected` label and `findings/publicFindingStates.ts`'s derived states, never the demo
   branch's older hand-kept versions.
5. **The salvage rows are `S1`–`S10`; the contract items are also called S1 and S2.** They are
   unrelated. A bare `S1` in the queue table means the salvage row; contract items are always
   written "contract S1" or named (`entity_type`, the `p` suffix).

One file, one queue. This replaced `docs/status/api.md` and `docs/status/web.md`, which never
reached `main` — they existed only on `docs/status-austine-lane`, so there was nothing to delete.
Every row below carries an `[api]` or `[web]` tag; the commands take no argument and read this file
(`docs/status/COMMANDS.md`).

## Open PR (one at a time)

**None.** Row 1's first half — **A-02a (C1 + contract S2)** — is committed on
`feat/web-a02-remainder-c1-s1`, rebased on `dbfaa39`, and waits for `/ship` to push and open it.

Merging stays hand-gated: root `CLAUDE.md` §6.0 forbids an agent merging a PR, and ADR-015's
Access-lane carve-out does not reach the permission layer that reads it. Austine runs
`gh pr merge <n> --squash --delete-branch`.

## Merged since last update

Nothing of this lane's. `origin/main` moved to Devyan's #119 (ADR-016), then #120 and #121 (the
Bible 7.1 source catalogue, written into `core.sources`). All three are `services/ingest/**` and
`docs/`, so no Access path moved and no contract changed. ADR-016 still raises the value of S6b's
not-measured tip: the water register is hand-curated and nothing is estimated, so coverage will be
thin and more variables arrive `null_not_measured` or provisional.

| PR | Task IDs | One line |
|---|---|---|
| #117 | A-02 part | `detected` added to the finding states; `contested` no longer described as viewer-visible; `tiles.md` limiter, 429 body and §9 ownership corrected. |
| #116 | — | The two Access status files merged into this one queue. |
| #115 | the command loop, ADR-015 | The five no-argument Access commands, hand-gated per ADR-015. |
| #114 | B1b-ii (design) | Self-hosted OFL woff2 subsets and base typography. |
| #113 | B1b-i (design) | `b1`–`b4` score ramp, Tailwind v4 `@theme` tokens, re-runnable contrast script. |
| #112 | A1 (A-10, DEC-13) | `GET /api/v1/me` — identity and capability, session only. |
| #95 | B1a-ii (design) | `DESIGN-mapping.md` — what we take from the reference and what we change. |
| #94 | B1a-i (design) | `DESIGN.md` + the `docs/design/reference/` ignore rule. |
| #93 | A0 (A-08, ADR-013) | Every non-public finding hidden from viewers, in the fixtures and the mock. |
| #74 | A-26 | Dates formatted "14 Sep 2026" in Africa/Nairobi. |
| #73 | A-13 | Partly-verified and cannot-assess roads given their own layers. |

So **A0, A1, B1a and B1b are done**, and A-02 is part-done: #117 landed its description-only slice.

Two Core merges still shape what this lane must do:

- **#88 `MODULES_ENABLED` / `app/Engine/ModuleFlags.php`** — a module can be switched off
  server-side. `GET /entities?module=` and `GET /lenses` must read the flag rather than list every
  module unconditionally, so a disabled module cannot be requested through the API. Lands in A6
  and A8.
- **#91 CORS is switched off** (`apps/api/config/cors.php`). Nothing changes for A2–A11 — the
  browser reaches the API through the same-origin dev proxy from #69 — but no endpoint of ours may
  be reached cross-origin, and no PR of ours re-enables CORS to work around a proxy gap.

## In progress

**Row 1 split into A-02a and A-02b.** C1 and contract S2 had to move together, because the fixtures
computed the status and the `p` suffix from one flag; contract S1 is independent, so it is its own
PR.

**A-02a — C1 + contract S2 — committed on `feat/web-a02-remainder-c1-s1`, not yet pushed.** The contract now
carries the engine's `scored · provisional · cannot_assess` and `tiles.md` §5 quotes
`lenses/README.md` rather than proposing a rule. Behaviour changed with it: `p` was raised when the
gate was provisional **or** coverage was below 1, and is now provisional only, and only where the
lens weights the variable above 0. `fixtures/lensApply.ts` decides it, since "weight above 0" cannot
be answered without the lens. Three new tests pin it.

**A-02b — contract S1 — not started.** `entity_type` is on 25 lines across 12 files, and it also
has to move the map layer filters, which today match `'road_segment'` and `'water_point'`. S5a
restyles those same filters, so A-02b lands first or the two collide.

## Next, in order

**The `[web]` items are unpaused.** A2 finishes first — the salvage needs C1's statuses and
A-02b's `entity_type` — then the tracks interleave as before, api unblockers ahead of the S rows
that need them. **Never two open PRs at once.** The tail is all `[web]` because the api track runs
out of items first — that is the rule working, not a breach of the alternation.

Rows **S1–S10** replace the old B1c, B2a–c, B3a–c, B4 and B5a. Each one reapplies one slice of
`feat/web-demo` onto a branch cut from fresh `origin/main`, under rule 4.

**Ten slices became fourteen PRs.** The demo branch is +3994/−355 over 60 files; at ≤ 400 changed
lines that is eleven PRs minimum before any rebase churn, so S4, S5, S6 and S10 are split, and the
split is recorded here per `CLAUDE.md` §6.4. The row count moved; the ten slices did not.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[api]` | **A-02b — contract S1 only.** `entity_type` → `point · parcel · segment · area` + `module`, including the map layer filters. A-02a (C1 + contract S2) is committed and awaiting `/ship`. Unblocks S5a–c and S6a/b | A-02 |
| 2 | `[web]` | **S1** — colour tokens + score ramp: `styles/tokens.css`, `styles/scoreRamp.ts`, `index.css`, `map/style/scale.ts` and its test. **Fonts are not in this row** — #114 already self-hosted Public Sans and Bricolage Grotesque, and the demo branch never touched them | design |
| 3 | `[api]` | A4a — fixtures compute coverage exactly as the engine does, weights from `weights.yml` (C11), plus C14 `gate_status`. Unblocks S6b | A-08 pre-req |
| 4 | `[web]` | **S2** — UI primitives: BrandMark, NoticePill, SegmentedControl, TabBar, icon set | design |
| 5 | `[api]` | A3 — A-07a/b scored resources; the base resource throws on a score without coverage + confidence | A-07 |
| 6 | `[web]` | **S3** — shell: header, tab bar, banner pill, footer, page grid, role switch | A-12 |
| 7 | `[api]` | A4b — `SyntheticDemoSeeder` + `fixtures:export` | A-08 pre-req |
| 8 | `[web]` | **S4a** — map controls row, layer toggles, lens selector replaced | A-12 |
| 9 | `[api]` | A5 — `GET /entities/{id}`, `/entities/{id}/scores` | A-08 |
| 10 | `[web]` | **S4b** — legend, how-to-read, **place register under rule 3** — entity data only, no finding indicator, `PlaceRegister.leak.test.tsx` required and mutation-verified | A-12 |
| 11 | `[api]` | A6 — `GET /entities` (bbox required, ≤500), `/entities/{id}/flags`, `/lenses`; held-finding invisibility tests on every path | A-08, A-19 |
| 12 | `[web]` | **S5a** — basemap palette, road and line styling: `style/flavor.ts`, `style/layers.ts`, `style/baseStyle.ts`. Needs A-02b's layer filters | design |
| 13 | `[api]` | A7 — Scramble at `/api/v1/openapi.json`; `gen:api` reads it, committed snapshot as the CI fallback | A-06 |
| 14 | `[web]` | **S5b** — pin sprite + hover/selection emphasis. **Replaces `pinEmphasis.ts`'s NUL sentinel** — see the Gate | design |
| 15 | `[api]` | A8 — `lens:sync` validation, `LensApplier`, `lens:apply --all` | A-11 |
| 16 | `[web]` | **S5c** — tooltip, selection fit, `MapView` wiring: `MapTooltip.tsx`, `featureBounds.ts` + test | design |
| 17 | `[api]` | A9 — MVT exactly per `tiles.md` + parity test against the frontend | A-09 |
| 18 | `[web]` | **S6a** — Passport shell: `EntityPanel`, `Badges`, `Bars`, `VariableRow`, visuals only. Needs C1 | A-16 |
| 19 | `[api]` | A10 — `VITE_DATA_SOURCE=api` switch + the `synthetic` flag | A-08 follow-up |
| 20 | `[web]` | **S6b** — completeness block, sub-variable accordion, and the "what would improve this" tip on not-measured rows from `null_reason`. Needs A4a's C14 | A-17 |
| 21 | `[api]` | A11 — broadcast channels. Last, after A2–A10 merge | A-21 |
| 22 | `[web]` | **S7** — finding detail + the visible analyst preview; sentences composed from `declared`/`observed` + `src/copy` (C7). Takes #117's `detected` label and derived states, not the demo's | A-18 |
| 23 | `[web]` | **S8** — Evidence and limits on one screen: `routes/EvidenceRoute.tsx` + `copy/evidence.ts` | A-18 |
| 24 | `[web]` | **S9** — motion, with `ui/reducedMotion.ts` respected on every animated surface | design |
| 25 | `[web]` | **S10a** — `npm run demo` + `e2e/demo.spec.ts`, the offline proof | design |
| 26 | `[web]` | **S10b** — `docs/design/DEMO.md` + the cheat sheet | design |
| 27 | `[web]` | B2-rest — Button, Card and a real Tooltip primitive. The demo never built them; raise only when a row needs one | design |
| 28 | `[web]` | B3c-rest — URL state for **centre and zoom only**; lens, layers and selected entity already ride S4a and S6a | A-14 |
| 29 | `[web]` | B1c — Playwright visual harness + baselines, on `test/web-visual-regression-harness`. **Moved after S10, not replaced:** it is not on the demo branch, and baselines shot before S1–S9 would be re-shot immediately | design |
| 30 | `[web]` | B5b — states matrix + tile-failure retry | A-24 |
| 31 | `[web]` | B6 — sign-in S7. Unblocked: `GET /me` merged as #112 | A-10 |
| 32 | `[web]` | B7a — review queue S4 | A-22 |
| 33 | `[web]` | B7b — review actions S5; buildable against the real `/flags`, 404-not-403 behaviour kept | A-22 |
| 34 | `[web]` | B8a — accessibility | A-25 |
| 35 | `[web]` | B8b — 390 px | A-25 |

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

- **S2's `p` ruling is applied, not deferred.** `tiles.md` §5 moved to match `lenses/README.md` and
  the merged engine. Devyan owns the vocabulary and may still object.
- **`detected` has a display label, though `docs/CONTEXT.md` gives it none.** #117 gave it
  `'Just detected (analysts only)'` so `FINDING_STATUS` is total over the contract's seven states
  and `FindingDetail` cannot render `undefined`. Analyst-only copy; one line to change.
- **DEC-20 no longer blocks the shell (S3).** The entity panel is **"Passport"**. Variable names come from
  `docs/CONTEXT.md` verbatim — Activity · Record vs reality · Momentum · Resource security ·
  Access — with CONTEXT.md's question as the tooltip, not as the label. The header reads
  **"Navuuna"**.
  Already true in code: `apps/web/src/copy/labels.ts` holds `PANEL_NAME`, `VARIABLE_LABELS` and
  `VARIABLE_TOOLTIPS`, and `labels.test.ts` pins all three, so a change of mind is a one-file
  change. Devyan owns `docs/CONTEXT.md`; this takes its words rather than inventing any.

## Contract changes between the tracks

#117 landed the description-only slice: C10, C10b and the `tiles.md` §7 limiter and §9 ownership
corrections. **A-02a applies C1 and S2**, which had to move together. Reviewers Khillon + Devyan
as FYI.

- **S1 `entity_type` — ruled, still to apply; it is A-02b.** The API serves the storage vocabulary
  `point · parcel · segment · area` plus `module`, as Bible §6.1 and §11 already define
  (`GET /entities?module=&type=`). The client composes display names in `src/copy/labels.ts`. Wards
  are `entity_type=area`; their module is read from what Devyan's loader writes — **not invented
  here**. The map layer filters move with it.
- **S2 `p` suffix — applied in A-02a.** `p` marks a weighted variable that is **provisional** (its
  gate was unmeasured) and nothing else: a failed gate is `ca`, because a failed gate makes the
  variable `cannot_assess` (`GateState::Failed` → `VariableStatus::CannotAssess`, and the
  `variable_scores_gate_matches_status_check` CHECK), and coverage below 1 alone adds nothing —
  coverage shows through "X of Y" beside the score (DEC-15). `tiles.md` §5 was the side that moved,
  and `fixtures/lensApply.ts` is now the single place that decides `p`.
- **C1 — applied in A-02a.** The engine's statuses `scored · cannot_assess · provisional`, in the
  contract, the fixtures, `copy/labels.ts`, `panel/Badges.tsx` and `panel/VariableRow.tsx`. The
  badge means **provisional only**; coverage below 1 shows through "X of Y", never the badge. The
  word on screen stays "Partly verified" pending `apps/web/README.md` open question (b).
  `map/style/scale.ts`'s naming lands in S1, not here.
- **C14 `gate_status` — newly measured, deferred to A4a.** The contract says
  `[measured, provisional, cannot_assess]` and required; `App\Engine\GateState` and the
  `variable_scores_gate_status_check` CHECK say `passed · failed · unmeasured`, nullable and
  non-null only for variables 1 and 2. So `VariableRow.tsx`'s `gate_status === 'provisional'` can
  never be true against a real API. Fixing it moves fixture semantics for V3–V5, so it rides A4a.
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
- **Closed:** `docs/adr/005-*` was cited by `apps/web/CLAUDE.md` and `tiles.md` but did not exist.
  Recorded in #92.

## Design: remaining differences from the reference

B1a and B1b have merged, so the tokens, ramp and fonts are real rather than planned. The eleven
deliberate deviations and their reasoning live in `docs/design/DESIGN-mapping.md` §2:
red/amber/green status encoding dropped; no bare scores; no dash for missing data; "asset" banned;
no cause-or-intent copy; held findings invisible; fonts actually loaded; real hover and pressed
states; four light-theme contrast failures fixed; proper tab semantics and roving tabindex; URL
state.

Ten things deliberately **not** built are in §3 of the same file, each with what would unblock it,
so no tab is ever rendered that leads nowhere. The decided ones, kept here so they are not
re-litigated:

- **Place register: allowed, under rule 3.** Entity data only. It reads `GET /entities`, which
  never carries finding state, and shows a viewer no finding indicator of any kind — including never
  hiding a row, since absence is itself a signal. `PlaceRegister.leak.test.tsx` is required and
  already exists on `feat/web-demo`, mutation-verified. Lands with S4b, row 10.
- **Dark theme:** recorded, not built. Thirty hex values in `DESIGN.md` §1.2; light theme only.
- **Raw-response `<details>`:** adopted but backlogged until A10, and then it renders the exact JSON
  the client fetched — no client-side composition.
- **Source chips** yes; the five-dot confidence glyph no.
- **`--line`, `--water`, `--new` and the notice colour** carry no status meaning. Text ≥ 4.5:1,
  UI ≥ 3:1. Red is reserved for system errors — never a score, never a finding.

## Unmerged local branches

This repo squash-merges, so `git branch --no-merged` lists merged work too. Only these four still
carry content that is not on `main`:

| Branch | Head | What it is |
|---|---|---|
| `feat/web-demo` | `28d2f7f` | The salvage source: 11 commits, **+3994/−355 across 60 files**, no remote, checked out in the `navuuna-demo` worktree. Merge-base `886bb57`, so it **does not contain #117**. Rule 4 governs it; the three regressions behind that rule are in the Gate. Tag `demo-ready` is at `3d6a911` and is **one commit short** of the branch — cherry-pick from the branch, not the tag. |
| `test/web-visual-regression-harness` | `3078c36` | B1c work — harness page, token-name test, four baselines. +671 lines. |
| `fix/web-scale-fallback-and-line-styles` | `e164bba` | **Unverified.** Looks superseded by #66 and #73, but still shows unique content in `map/style/layers.ts` and `fixtures/generate.ts`. Check before deleting. |
| `docs/contract-detected-state-and-tiles-fixes` | `a3741d5` | **Superseded by #117 — safe to delete.** It was the A2 partial; #117 replaced its hand-kept guard arrays with a `Record` over the generated union. |

`docs/status-austine-lane` (`6eaee6c`) is superseded by this file and can be abandoned. The rest of
the local branches correspond to merged PRs and are safe to prune.

## Gate

Only commands that actually ran, with their real results. Live CI is `gh pr checks <n>`.

- **A-02a, before its push:** `npm run typecheck`, `npm run lint` and `npm run build` clean ·
  `npm run test` → 125 passed / 18 files · `npm run gen:api` re-run leaves the same 5+/2−
  `schema.d.ts` diff, so it is the generator's output. No `apps/api/**` change, so the php and
  python gates do not apply.
- **A fail-open hole closed on the way:** no `apps/web` test file was typechecked — both tsconfigs
  excluded `*.test.*`, so a renamed contract literal survived silently in tests, the same family of
  hole #117 fixed. Excludes removed; the 1 real error from this rename and 3 pre-existing ones in
  `map/style/scale.test.ts` are fixed here.
- **#117 merged as `dc81cfc`** at 225 changed lines, CI 9 of 9 green — the same nine checks this
  branch must pass. `entity_type` is 25 lines / 12 files on it; that is A-02b's size.
- **`feat/web-demo` re-measured against fresh `origin/main` (`dbfaa39`): 62 files, +3743/−450.**
  Merge-base is still `886bb57`, so it predates #117 · no `detected` in its `labels.ts` ·
  `partly_verified` across 7 files. **Correction:** an earlier line here said the branch has no
  `findings/publicFindingStates.ts`. It does — the file arrived in #93 (`b05caec`) and is
  **byte-identical** to main's. #117 rewrote its *test* and `FindingDetail.tsx`, so the demo
  carries the pre-#117 versions of `publicFindingStates.test.ts` (41 lines) and
  `FindingDetail.tsx` (116 lines). Rule 4 stands, with a narrower target: the regression to guard
  against is the missing `detected` label and those two older files, not a missing module.
- **1743 lines of the demo were never committed.** The `navuuna-demo` worktree held +749/−101
  tracked and 994 untracked lines — the tokens and ramp revision, the pin sprite and emphasis, the
  tooltip, the viewport controls, the icon set and `reducedMotion.ts`. S1, S5b and S9 are made of
  it. Committed as `28d2f7f` so the worktree is clean and removable; `git worktree remove` would
  have refused while it was dirty, and `--force` would have destroyed it.
- **`map/pinEmphasis.ts` is binary to git.** Its `MATCHES_NOTHING` sentinel is a literal NUL byte,
  so the file diffs as `Bin 0 -> 5851 bytes` and cannot be reviewed. S5b replaces the sentinel with
  an ordinary unusable id before the file lands.
- **Measured slice sizes** (changed lines against `886bb57`): tokens + ramp 354 · `ui/` 388 ·
  `map/` 2046 · `panel/` 516 · routes + evidence copy 319 · findings 159 · demo script, spec and
  DEMO.md 632. That is where the S4, S5, S6 and S10 splits come from.
- **S1 re-measured against fresh `origin/main`: +327/−27 across 5 files**, under the 400 limit with
  room for rebase churn. Fonts are confirmed absent from it: `styles/fonts.css`, the two woff2
  subsets and `OFL.txt` are already on `main` from #114, and the demo branch's `index.css` only
  `@import`s them.

**Not claimed:** nothing here is verified against a live API or deployed to staging.
