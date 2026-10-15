# Status — Access lane (one queue)

Anchor: main @ `dbfaa39` · latest PR seen #121 (Devyan's, merged)

## Standing rules

1. **A status update never gets its own PR.** `/wrap` commits this file onto the next queue item's
   branch, cut from `origin/main`, and `/ship` carries it there. A status-only PR is closed, not
   iterated on — #118 was closed unmerged under this rule and its content is here.
2. **Keep this file short.** No commentary on pinned shas or runs; live values come from `gh`.
3. **The place register is allowed**, with entity data only, no finding indicator of any kind, and
   `PlaceRegister.leak.test.tsx` required. Settled, not an open question.
4. **`feat/web-demo` is a DESIGN SOURCE, read with `git show` and `git diff`. Never merged, never
   pushed.** The demo is done; the real frontend is now built from its design. Every S row cuts a
   fresh branch from `origin/main`, **rebuilds** one piece at ≤ 400 changed lines, and keeps #117's
   guards: the `detected` label and `findings/publicFindingStates.ts`'s derived states, never the
   demo branch's older versions. The branch has no remote and must not get one — it is backed up
   offline at `../navuuna-demo-backup.bundle` (branch + `demo-ready` tag, verified complete).
5. **The S rows are `S1`–`S10`; the contract items are also called S1 and S2.** They are
   unrelated. A bare `S1` in the queue table means the rebuild row; contract items are always
   written "contract S1" or named (`entity_type`, the `p` suffix).
6. **No Viewer/Analyst toggle anywhere.** Visibility follows the signed-in role from `GET /me`
   (#112). The demo's `ui/RoleSwitch.tsx` is deliberately **not** rebuilt — a client-side role
   switch is a held-finding leak waiting to happen.

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

**The `[web]` items are unpaused.** A2 finishes first — the rebuild needs C1's statuses and A-02b's
`entity_type` — then the tracks interleave, api unblockers ahead of the web rows that need them.
**Never two open PRs at once.** The tail is all `[web]` because the api track runs out of items
first — that is the rule working, not a breach of the alternation.

Rows **S1–S10** replace the old B1c, B2a–c, B3a–c, B4 and B5a. Each one rebuilds one piece of the
demo's design on a branch cut from fresh `origin/main`, under rule 4.

**Two changes of shape from the demo queue.** `A10` moves to right after `A6`, so the screens read
the real API on seeded data early and fixtures fall back to tests and local dev only. `B6` and
`B7a/b` follow `S7`, so real users and the real `/flags` arrive while the finding surfaces are
fresh.

**Nine slices became sixteen PRs.** The demo branch is 62 files, +3743/−450 against `dbfaa39`; at
≤ 400 changed lines that is eleven PRs minimum before rebase churn, so S4, S5 and S6 are split.
Every split row is measured in the Gate. Recorded per `CLAUDE.md` §6.4 — the row count moved, the
nine slices did not.

**Deliberately not rebuilt:** `scripts/demo.mjs` and the `npm run demo` script (49 lines),
`docs/design/DEMO.md` and its cheat sheet, `e2e/compare.spec.ts` (112 lines — it copies reference
shots for human review and asserts no pixel), and `ui/RoleSwitch.tsx` under rule 6.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[api]` | **A-02b — contract S1 only.** `entity_type` → `point · parcel · segment · area` + `module`, including the map layer filters. A-02a (C1 + contract S2) is committed and awaiting `/ship`. Unblocks S5a and S6a | A-02 |
| 2 | `[web]` | **S1** — colour tokens + score ramp: `styles/tokens.css`, `styles/scoreRamp.ts`, `index.css`, `map/style/scale.ts` + test. **Fonts need no work** — #114 already self-hosted Public Sans and Bricolage Grotesque (`styles/fonts.css`, two woff2 subsets, `OFL.txt`); the demo only `@import`s them. Confirm, don't re-add | design |
| 3 | `[api]` | A3 — A-07a/b scored resources; the base resource throws on a score without coverage + confidence | A-07 |
| 4 | `[web]` | **S2** — UI primitives: BrandMark, NoticePill, SegmentedControl, TabBar, icon set | design |
| 5 | `[api]` | A4a — fixtures compute coverage exactly as the engine does, weights from `weights.yml` (C11), plus C14 `gate_status`. Unblocks S6b | A-08 pre-req |
| 6 | `[web]` | **S3** — app shell: header, tab bar, routing, footer, page grid. **Illustrative banner driven by the API's `synthetic` flag**; fixture mode always shows it, so this lands before A10 and reads the real flag once A10 is in. No role switch (rule 6) | A-12 |
| 7 | `[api]` | A4b — `SyntheticDemoSeeder` + `fixtures:export`. Gives A10 its seeded data and S3 its real `synthetic` flag | A-08 pre-req |
| 8 | `[web]` | **S4a** — map controls row, viewport controls, layer toggles, lens selector replaced | A-12 |
| 9 | `[api]` | A5 — `GET /entities/{id}`, `/entities/{id}/scores` | A-08 |
| 10 | `[web]` | **S4b** — legend + how-to-read | A-12 |
| 11 | `[api]` | A6 — `GET /entities` (bbox required, ≤500), `/entities/{id}/flags`, `/lenses`; held-finding invisibility tests on every path | A-08, A-19 |
| 12 | `[api]` | **A10 — `VITE_DATA_SOURCE=api` switch + the `synthetic` flag.** Moved up to here: from this row on, the screens read the real API on seeded data, and fixtures are for tests and local dev only | A-08 follow-up |
| 13 | `[web]` | **S4c** — **place register under rule 3** — entity data only, no finding indicator of any kind, `PlaceRegister.leak.test.tsx` required and mutation-verified, plus `readVisiblePlaces.ts` | A-12 |
| 14 | `[web]` | **S5a** — basemap and road/line styling: `style/layers.ts`, `style/baseStyle.ts`. Needs A-02b's layer filters | design |
| 15 | `[api]` | A7 — Scramble at `/api/v1/openapi.json`; `gen:api` reads it, committed snapshot as the CI fallback | A-06 |
| 16 | `[web]` | **S5b** — palette + pin sprite: `style/flavor.ts`, `style/pinSprite.ts` | design |
| 17 | `[api]` | A8 — `lens:sync` validation, `LensApplier`, `lens:apply --all` | A-11 |
| 18 | `[web]` | **S5c** — pin interactions, hover/selection emphasis, tooltip. **Rewrites `pinEmphasis.ts`'s NUL sentinel** as an ordinary unusable id — see the Gate | design |
| 19 | `[api]` | A9 — MVT exactly per `tiles.md` + parity test against the frontend | A-09 |
| 20 | `[web]` | **S5d** — selection fit + `MapView` wiring: `featureBounds.ts` + test, `MapView.tsx` | design |
| 21 | `[web]` | **S6a** — Passport shell: `EntityPanel`, `Badges`, `Bars`, `VariableRow`, visuals only. Needs C1 | A-16 |
| 22 | `[web]` | **S6b** — completeness block, sub-variable accordion, and the "what would improve this" tip on not-measured rows from `null_reason`. Needs A4a's C14 | A-17 |
| 23 | `[web]` | **S7** — finding detail; sentences composed from `declared`/`observed` + `src/copy` (C7). **Visibility from the signed-in role via `GET /me`, no toggle** (rule 6). Takes #117's `detected` label and derived states, never the demo's older versions | A-18 |
| 24 | `[web]` | B6 — sign-in. Unblocked: `GET /me` merged as #112 | A-10 |
| 25 | `[web]` | B7a — review queue, on the real `/flags` from A6 | A-22 |
| 26 | `[web]` | B7b — review actions; 404-not-403 behaviour kept | A-22 |
| 27 | `[api]` | A11 — broadcast channels. Last, after A2–A10 merge | A-21 |
| 28 | `[web]` | **S8** — Evidence and limits on one screen: `routes/EvidenceRoute.tsx` + `copy/evidence.ts` | A-18 |
| 29 | `[web]` | **S9** — motion, with `ui/reducedMotion.ts` respected on every animated surface | design |
| 30 | `[web]` | **S10** — `e2e/demo.spec.ts` ported as **`e2e/click-path.spec.ts`**, plus the `e2e/smoke.spec.ts` update. `npm run demo` and `DEMO.md` are not ported | design |
| 31 | `[web]` | B5b — error and empty states matrix + tile-failure retry | A-24 |
| 32 | `[web]` | B8a — accessibility | A-25 |
| 33 | `[web]` | B8b — 390 px | A-25 |
| 34 | `[web]` | B2-rest — Button, Card and a real Tooltip primitive. The demo never built them; raise only when a row needs one | design |
| 35 | `[web]` | B3c-rest — URL state for **centre and zoom only**; lens, layers and selected entity already ride S4a and S6a | A-14 |
| 36 | `[web]` | B1c — Playwright visual harness + baselines, on `test/web-visual-regression-harness`. **Moved after S10, not replaced:** it is not on the demo branch, and baselines shot before S1–S9 would be re-shot immediately | design |

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

- **The Viewer/Analyst toggle is dropped, not rebuilt** (rule 6). The demo shipped
  `ui/RoleSwitch.tsx` so one browser could show both audiences; the real frontend takes the role
  from `GET /me` and nothing else. A client-side switch over held findings is a leak path, and
  ADR-013 puts that invisibility on the server. The demo's 16 changed lines there are not ported.
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
  already exists on `feat/web-demo`, mutation-verified. Lands with **S4c, row 13**.
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
| `feat/web-demo` | `28d2f7f` | **The design source** (rule 4): 11 commits, 62 files, +3743/−450 against `dbfaa39`. **No remote and it must not get one.** The `navuuna-demo` worktree is removed; read the branch with `git show feat/web-demo:<path>` and `git diff origin/main feat/web-demo -- <path>`. Merge-base `886bb57`, so it **does not contain #117**. Tag `demo-ready` is at `3d6a911`, **one commit short** of the branch — read the branch, not the tag. Backed up offline at `../navuuna-demo-backup.bundle`. |
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
- **Demo cleanup ran, with one loss to report.** `../navuuna-demo-backup.bundle` created and
  `git bundle verify`d: complete history, both refs (`feat/web-demo` `28d2f7f`, `demo-ready`
  `3d6a911`). The worktree was clean (`git status --porcelain` empty, no untracked files) and
  `git worktree remove` succeeded without `--force`; the branch and tag survive in this repo.
  **But the worktree was removed before the request to copy a backup video out of it, and no
  `.webm` exists anywhere on this machine.** A search of the whole profile for `*.webm *.mp4 *.mov
  *.mkv *.gif` over 100 KB found no Navuuna demo recording — the newest screen recordings are from
  2026-09-17 — and the Recycle Bin (2040 items) has nothing matching. Whether a video ever existed
  cannot now be determined: the demo branch never references `.webm`, but `apps/web/.gitignore`
  ignores `test-results/`, where Playwright writes video, so an ignored recording would have been
  invisible to `git status` and destroyed by the removal. **Treat the video as gone. The code is
  not** — the bundle and the branch both hold it.
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
- **Every row measured against fresh `origin/main` (`dbfaa39`), changed lines.** This is the whole
  basis of the splits; no row is estimated except S9.

  | Row | Files | Lines |
  |---|---|---|
  | S1 | `tokens.css` 139 · `scoreRamp.ts` 16 · `index.css` 87 · `scale.ts` 62 · `scale.test.ts` 50 | **354** |
  | S2 | `icons` 103 · `TabBar` 98 · `SegmentedControl` 69 · `NoticePill` 23 · `BrandMark` 22 | **315** |
  | S3 | `MapRoute` 69 · `App.tsx` 38 · `IllustrativeBanner` 20 · `mockServer` 7 · `AttributionFooter` 5 · `App.test` 5 | **144** |
  | S4a | `useMapLayers` 75 · `applyLayerVisibility` 72 · `MapViewportControls` 81 · `MapControls` 45 · `lenses` 28 · `LensSelector` −29 | **330** |
  | S4b | `MapLegend` 89 · `MapHowToRead` 66 | **155** |
  | S4c | `PlaceRegister.leak.test` 135 · `PlaceRegister` 59 · `readVisiblePlaces` 51 | **245** |
  | S5a | `style/layers.ts` 301 · `style/baseStyle.ts` 16 | **317** |
  | S5b | `style/pinSprite.ts` 195 · `style/flavor.ts` 126 | **321** |
  | S5c | `pinEmphasis.ts` 181 · `attachPinInteractions` 112 · `MapTooltip` 54 | **347** |
  | S5d | `MapView.tsx` 192 · `featureBounds.test` 105 · `featureBounds` 65 | **362** |
  | S6a | `Bars` 74 · `VariableRow` 68 · `EntityPanel` 61 · `Badges` 17 · `LazyEntityPanel` 2 | **222** |
  | S6b | `SubVariableAccordion` 96 · `readingCompleteness.test` 90 · `readingCompleteness` 62 · `CompletenessBlock` 52 | **300** |
  | S7 | `FindingDetail` 116 · `FindingDetail.test` 73 · `publicFindingStates.test` 41 · `labels.ts` 40 · `labels.test` 12 | **282** |
  | S8 | `EvidenceRoute` 174 · `copy/evidence` 76 | **250** |
  | S9 | `reducedMotion.ts` 32 + applying it on the animated surfaces | **~150 est** |
  | S10 | `click-path.spec.ts` (from `demo.spec.ts` 195) · `smoke.spec.ts` 7 | **202** |

- **Fonts need no rebuild, verified two ways:** `styles/fonts.css`, `public-sans-latin-variable.woff2`,
  `bricolage-grotesque-latin-variable.woff2` and `OFL.txt` are on `origin/main` from #114, and
  `git diff origin/main feat/web-demo -- apps/web/public apps/web/src/index.css` shows the demo
  touching only `index.css`, whose font handling is a single `@import './styles/fonts.css'`.

**Not claimed:** nothing here is verified against a live API or deployed to staging.
