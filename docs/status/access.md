# Status — Access lane (one queue)

Anchor: main @ `dbfaa39` · latest PR seen #137 (Devyan's, open)

## Standing rules

1. **A status update never gets its own PR.** `/wrap` commits this file onto the next queue item's
   branch, cut from `origin/main`, and `/ship` carries it there. A status-only PR is closed, not
   iterated on — #118 was closed unmerged under this rule and its content is here.
2. **Keep this file short.** No commentary on pinned shas or runs; live values come from `gh`.
3. **The place register is allowed**, with entity data only, no finding indicator of any kind, and
   `PlaceRegister.leak.test.tsx` required. Settled, not an open question.
4. **`feat/web-demo` is a design source, read with `git show` and `git diff`. Never merged, never
   pushed, and it must never get a remote.** Every S row cuts a fresh branch from `origin/main` and
   **rebuilds** one piece at ≤ 400 changed lines, keeping #117's guards: the `detected` label and
   `findings/publicFindingStates.ts`'s derived states, never the demo's older versions. Backed up
   offline at `../navuuna-demo-backup.bundle`.
5. **The rebuild rows are `S1`–`S10`; two contract items are also called S1 and S2.** Unrelated. A
   bare `S1` means the rebuild row; contract items are written "contract S1" or named.
6. **No Viewer/Analyst toggle anywhere.** Visibility follows the signed-in role from `GET /me`
   (#112). The demo's `ui/RoleSwitch.tsx` is deliberately not rebuilt — a client-side switch over
   held findings is a leak path, and ADR-013 puts that invisibility on the server.

One file, one queue. Every row carries an `[api]` or `[web]` tag; the commands take no argument and
read this file (`docs/status/COMMANDS.md`).

## Open PR (one at a time)

**None of this lane's.** **A-02a (C1 + contract S2)** is committed on
`feat/web-a02-remainder-c1-s1`, rebased on `dbfaa39`, and waits for `/ship`.

Eleven of Devyan's `ingest` PRs (#122–#137) are open and mergeable. They are another lane and do
not count against this queue's one-at-a-time rule.

Merging stays hand-gated: root `CLAUDE.md` §6.0 forbids an agent merging a PR, and ADR-015's
Access-lane carve-out does not reach the permission layer that reads it. Austine runs
`gh pr merge <n> --squash --delete-branch`.

## Merged since last update

Nothing of this lane's; `origin/main` moved on Devyan's #119–#121 only, all `services/ingest/**`
and `docs/`, so no Access path moved and no contract changed. ADR-016 raises the value of S6b's
not-measured tip: the water register is hand-curated and nothing estimated, so coverage will be
thin and more variables arrive `null_not_measured` or provisional.

`git log --grep` has the full list. The ones that still constrain current work:

| PR | What still binds |
|---|---|
| #117 | `detected` in the finding states; derived `publicFindingStates.ts`; `tiles.md` limiter and §9 ownership. **S7 takes these, not the demo's.** |
| #114 · #113 | Self-hosted Public Sans + Bricolage Grotesque; `b1`–`b4` ramp and `@theme` tokens. **S1 confirms, never re-adds.** |
| #112 | `GET /api/v1/me` — identity and capability, session only. Unblocks rule 6, S7 and B6. |
| #93 | Every non-public finding hidden from viewers, in the fixtures and the mock. |
| #88 (Core) | `MODULES_ENABLED` / `ModuleFlags.php` — `GET /entities?module=` and `/lenses` must read the flag, so a disabled module cannot be requested. Lands in A6 and A8. |
| #91 (Core) | CORS off. The browser reaches the API through the same-origin dev proxy; no PR of ours re-enables CORS to work around a proxy gap. |

So **A0, A1, B1a and B1b are done**, and A-02 is part-done: #117 landed its description-only slice.

## In progress

**Row 1 split into A-02a and A-02b.** C1 and contract S2 had to move together — the fixtures
computed the status and the `p` suffix from one flag. Contract S1 is independent, so it is its own
PR.

**A-02a — committed, not yet pushed.** The contract carries the engine's
`scored · provisional · cannot_assess`, and `tiles.md` §5 now quotes `lenses/README.md` instead of
proposing a rule. Behaviour moved with it: `p` was raised when the gate was provisional **or**
coverage was below 1, and is now provisional only, and only where the lens weights the variable
above 0. `fixtures/lensApply.ts` decides it, since "weight above 0" needs the lens. Three tests pin
it.

**A-02b — not started.** `entity_type` is 25 lines across 12 files plus the map layer filters,
which today match `'road_segment'` and `'water_point'`. S5a restyles those same filters, so A-02b
lands first or the two collide.

## Next, in order

**The `[web]` items are unpaused.** A2 finishes first — the rebuild needs C1's statuses and A-02b's
`entity_type` — then the tracks interleave, api unblockers ahead of the web rows that need them.
**Never two open PRs at once.** The tail is all `[web]` because the api track runs out of items
first.

Rows **S1–S10** replace the old B1c, B2a–c, B3a–c, B4 and B5a, each rebuilding one piece of the
demo's design under rule 4. **A10 moved to after A6**, so the screens read the real API on seeded
data early and fixtures fall back to tests and local dev. **B6 and B7a/b follow S7**, so real users
and the real `/flags` arrive while the finding surfaces are fresh.

**Nine slices became sixteen PRs**, because the demo is 62 files and +3743/−450 against `dbfaa39`;
S4, S5 and S6 are split. Sizes are in the Gate. Recorded per `CLAUDE.md` §6.4.

**Deliberately not rebuilt:** `npm run demo` and `scripts/demo.mjs`, `DEMO.md` and its cheat sheet,
`e2e/compare.spec.ts` (copies reference shots for human review, asserts no pixel), and
`ui/RoleSwitch.tsx` under rule 6.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[api]` | **A-02b — contract S1 only.** `entity_type` → `point · parcel · segment · area` + `module`, including the map layer filters. Unblocks S5a and S6a | A-02 |
| 2 | `[web]` | **S1** — colour tokens + score ramp: `styles/tokens.css`, `styles/scoreRamp.ts`, `index.css`, `map/style/scale.ts` + test. Fonts need no work (#114) — confirm, don't re-add | design |
| 3 | `[api]` | A3 — A-07a/b scored resources; the base resource throws on a score without coverage + confidence | A-07 |
| 4 | `[web]` | **S2** — UI primitives: BrandMark, NoticePill, SegmentedControl, TabBar, icon set | design |
| 5 | `[api]` | A4a — fixtures compute coverage exactly as the engine does, weights from `weights.yml` (C11), plus C14 `gate_status`. Unblocks S6b | A-08 pre-req |
| 6 | `[web]` | **S3** — app shell: header, tab bar, routing, footer, page grid. Illustrative banner driven by the API's `synthetic` flag; fixture mode always shows it, so this lands before A10 and reads the real flag once A10 is in. No role switch (rule 6) | A-12 |
| 7 | `[api]` | A4b — `SyntheticDemoSeeder` + `fixtures:export`. Gives A10 its seeded data and S3 its real flag | A-08 pre-req |
| 8 | `[web]` | **S4a** — map controls, viewport controls, layer toggles, lens selector replaced | A-12 |
| 9 | `[api]` | A5 — `GET /entities/{id}`, `/entities/{id}/scores` | A-08 |
| 10 | `[web]` | **S4b** — legend + how-to-read | A-12 |
| 11 | `[api]` | A6 — `GET /entities` (bbox required, ≤500), `/entities/{id}/flags`, `/lenses`; held-finding invisibility tests on every path | A-08, A-19 |
| 12 | `[api]` | **A10 — `VITE_DATA_SOURCE=api` + the `synthetic` flag.** Moved up: from here the screens read the real API on seeded data, and fixtures are for tests and local dev only | A-08 follow-up |
| 13 | `[web]` | **S4c** — place register under rule 3: entity data only, no finding indicator of any kind, `PlaceRegister.leak.test.tsx` required and mutation-verified, plus `readVisiblePlaces.ts` | A-12 |
| 14 | `[web]` | **S5a** — basemap and road/line styling: `style/layers.ts`, `style/baseStyle.ts`. Needs A-02b's layer filters | design |
| 15 | `[api]` | A7 — Scramble at `/api/v1/openapi.json`; `gen:api` reads it, committed snapshot as the CI fallback | A-06 |
| 16 | `[web]` | **S5b** — palette + pin sprite: `style/flavor.ts`, `style/pinSprite.ts` | design |
| 17 | `[api]` | A8 — `lens:sync` validation, `LensApplier`, `lens:apply --all` | A-11 |
| 18 | `[web]` | **S5c** — pin interactions, hover/selection emphasis, tooltip. Rewrites `pinEmphasis.ts`'s NUL sentinel as an ordinary unusable id | design |
| 19 | `[api]` | A9 — MVT exactly per `tiles.md` + parity test against the frontend | A-09 |
| 20 | `[web]` | **S5d** — selection fit + `MapView` wiring: `featureBounds.ts` + test, `MapView.tsx` | design |
| 21 | `[web]` | **S6a** — Passport shell: `EntityPanel`, `Badges`, `Bars`, `VariableRow`, visuals only. Needs C1 | A-16 |
| 22 | `[web]` | **S6b** — completeness block, sub-variable accordion, and the "what would improve this" tip on not-measured rows from `null_reason`. Needs A4a's C14 | A-17 |
| 23 | `[web]` | **S7** — finding detail; sentences from `declared`/`observed` + `src/copy` (C7). Visibility from the signed-in role, no toggle (rule 6). Takes #117's `detected` and derived states | A-18 |
| 24 | `[web]` | B6 — sign-in. Unblocked: `GET /me` merged as #112 | A-10 |
| 25 | `[web]` | B7a — review queue, on the real `/flags` from A6 | A-22 |
| 26 | `[web]` | B7b — review actions; 404-not-403 behaviour kept | A-22 |
| 27 | `[api]` | A11 — broadcast channels. Last, after A2–A10 merge | A-21 |
| 28 | `[web]` | **S8** — Evidence and limits on one screen: `routes/EvidenceRoute.tsx` + `copy/evidence.ts` | A-18 |
| 29 | `[web]` | **S9** — motion, with `ui/reducedMotion.ts` respected on every animated surface | design |
| 30 | `[web]` | **S10** — `demo.spec.ts` ported as **`e2e/click-path.spec.ts`**, plus the `smoke.spec.ts` update | design |
| 31 | `[web]` | B5b — error and empty states matrix + tile-failure retry | A-24 |
| 32 | `[web]` | B8a — accessibility | A-25 |
| 33 | `[web]` | B8b — 390 px | A-25 |
| 34 | `[web]` | B2-rest — Button, Card and a real Tooltip primitive. The demo never built them; raise only when a row needs one | design |
| 35 | `[web]` | B3c-rest — URL state for centre and zoom only; lens, layers and selected entity ride S4a and S6a | A-14 |
| 36 | `[web]` | B1c — Playwright visual harness + baselines, on `test/web-visual-regression-harness`. Moved after S10, not replaced: it is not on the demo branch, and baselines shot before S1–S9 would be re-shot immediately | design |

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

- **The Viewer/Analyst toggle is dropped, not rebuilt** — rule 6 carries the reasoning.
- **S2's `p` ruling is applied, not deferred.** `tiles.md` §5 moved to match `lenses/README.md` and
  the merged engine. Devyan owns the vocabulary and may still object.
- **`detected` has a display label, though `docs/CONTEXT.md` gives it none.** #117 gave it
  `'Just detected (analysts only)'` so `FINDING_STATUS` is total over the contract's seven states
  and `FindingDetail` cannot render `undefined`. Analyst-only copy; one line to change.
- **DEC-20 no longer blocks the shell (S3).** The entity panel is **"Passport"**; the header reads
  **"Navuuna"**. Variable names come from `docs/CONTEXT.md` verbatim — Activity · Record vs
  reality · Momentum · Resource security · Access — with CONTEXT.md's question as the tooltip, not
  the label. `copy/labels.ts` holds all three and `labels.test.ts` pins them, so a change of mind
  is a one-file change.

## Contract changes between the tracks

#117 landed the description-only slice (C10, C10b, `tiles.md` §7 and §9). **A-02a applies C1 and
contract S2**, which had to move together. Reviewers Khillon + Devyan as FYI.

- **Contract S1 `entity_type` — ruled, still to apply; it is A-02b.** The API serves the storage
  vocabulary `point · parcel · segment · area` plus `module`, as Bible §6.1 and §11 already define.
  The client composes display names in `src/copy/labels.ts`. Wards are `entity_type=area`; their
  module is read from what Devyan's loader writes — **not invented here**. Layer filters move with
  it.
- **Contract S2 `p` suffix and C1 — applied in A-02a.** `p` marks a weighted variable that is
  **provisional** and nothing else: a failed gate is `ca`, because a failed gate makes the variable
  `cannot_assess`, and coverage below 1 adds nothing — coverage shows through "X of Y" (DEC-15).
  The badge means provisional only. The word on screen stays "Partly verified" pending
  `apps/web/README.md` open question (b).
- **C14 `gate_status` — deferred to A4a.** The contract says `[measured, provisional,
  cannot_assess]` and required; `GateState` and the CHECK say `passed · failed · unmeasured`,
  non-null only for V1 and V2. So `VariableRow.tsx`'s `gate_status === 'provisional'` can never be
  true against a real API. Fixing it moves fixture semantics for V3–V5, so it rides A4a.
- **C11 — ruled into A4a**, not A10, so fixture mode and the seeded database never diverge: the
  engine's `coverage` is weight-weighted and the fixtures must weight it the same way.
- **C5, C7, C13, R4, R7 — accepted.** The R4 test pinning Core's `inputs` keys is required.
- **Seeder — option 3 approved.** `generate.ts` → committed `synthetic.json` →
  `SyntheticDemoSeeder` as a plain loader, with a CI diff. Synthetic flag is
  `metadata {"synthetic": true}` plus "(synthetic)" source names; the API's `synthetic` is true when
  **any** contributing source is synthetic. The seeder refuses to run outside local/testing. Rollup
  and lens scores come from running the real commands — never hand-written rows.
- **No `resolved` finding exists in the fixtures**, though a viewer will see that state. One line,
  but it moves the fixture counts, so it rides A4a.
- **To Signal:** C7 means findings carry `declared`/`observed`/`gap` + `narrative`, not composed
  sentences — the gap wording stays in `docs/CONTEXT.md`. C13 drops `name`/`label` from the
  response, so labels stay in `apps/web/src/copy`.

## Design: remaining differences from the reference

The eleven deliberate deviations and their reasoning live in `docs/design/DESIGN-mapping.md` §2;
ten things deliberately not built are in §3, each with what would unblock it, so no tab is ever
rendered that leads nowhere. The decided ones, kept here so they are not re-litigated:

- **Place register: allowed, under rule 3.** It reads `GET /entities`, which never carries finding
  state, and shows a viewer no finding indicator of any kind — including never hiding a row, since
  absence is itself a signal. `PlaceRegister.leak.test.tsx` is required and already exists on the
  demo branch, mutation-verified. Lands with **S4c, row 13**.
- **Dark theme:** recorded, not built. Thirty hex values in `DESIGN.md` §1.2; light theme only.
- **Raw-response `<details>`:** adopted, backlogged until A10, and then it renders the exact JSON
  the client fetched — no client-side composition.
- **Source chips** yes; the five-dot confidence glyph no.
- **`--line`, `--water`, `--new` and the notice colour** carry no status meaning. Text ≥ 4.5:1,
  UI ≥ 3:1. Red is reserved for system errors — never a score, never a finding.

## Unmerged local branches

This repo squash-merges, so `git branch --no-merged` lists merged work too. Only these four still
carry content that is not on `main`:

| Branch | Head | What it is |
|---|---|---|
| `feat/web-demo` | `28d2f7f` | **The design source** (rule 4): 62 files, +3743/−450 against `dbfaa39`, no remote. The `navuuna-demo` worktree is removed; read it with `git show feat/web-demo:<path>`. Merge-base `886bb57`, so it **does not contain #117**. Tag `demo-ready` `3d6a911` is one commit short — read the branch, not the tag. |
| `test/web-visual-regression-harness` | `3078c36` | B1c work — harness page, token-name test, four baselines. +671 lines. |
| `fix/web-scale-fallback-and-line-styles` | `e164bba` | **Unverified.** Looks superseded by #66 and #73, but still shows unique content in `map/style/layers.ts` and `fixtures/generate.ts`. Check before deleting. |
| `docs/contract-detected-state-and-tiles-fixes` | `a3741d5` | **Superseded by #117 — safe to delete.** |

`docs/status-austine-lane` (`6eaee6c`) is superseded by this file. The rest correspond to merged
PRs and are safe to prune.

## Gate

Only commands that actually ran, with their real results. Live CI is `gh pr checks <n>`.

- **A-02a, before its push:** `npm run typecheck`, `lint` and `build` clean · `npm run test` → 125
  passed / 18 files · `npm run gen:api` re-run leaves the same `schema.d.ts` diff, so it is the
  generator's output. No `apps/api/**` change, so the php and python gates do not apply.
- **A fail-open hole closed on the way:** no `apps/web` test file was typechecked — both tsconfigs
  excluded `*.test.*`, so a renamed contract literal survived silently in tests, the same family of
  hole #117 fixed. Excludes removed; the 1 real error from this rename and 3 pre-existing ones in
  `map/style/scale.test.ts` are fixed here.
- **Rebuild row sizes, measured against `dbfaa39`, changed lines:** S1 354 · S2 315 · S3 144 ·
  S4a 330 · S4b 155 · S4c 245 · S5a 317 · S5b 321 · S5c 347 · S5d 362 · S6a 222 · S6b 300 ·
  S7 282 · S8 250 · S10 202. S9 is the only estimate (~150). Every row is under 400; that is where
  the S4, S5 and S6 splits come from.
- **The demo branch's guard gap is narrower than first recorded.** `publicFindingStates.ts` is on
  it, byte-identical to main's, from #93 — not missing. What is pre-#117 is the missing `detected`
  label, `publicFindingStates.test.ts` (41 lines) and `FindingDetail.tsx` (116 lines), plus
  `partly_verified` across 7 files. That is rule 4's real target.
- **Demo cleanup: the bundle is verified, the video is gone.** `../navuuna-demo-backup.bundle`
  passes `git bundle verify` with both refs and a complete history, and the worktree was clean when
  `git worktree remove` took it without `--force`. But it was removed before the request to copy a
  backup video out, and no `.webm` exists anywhere on the machine — a whole-profile search found no
  Navuuna recording and the Recycle Bin has nothing matching. Whether one ever existed cannot be
  determined: the branch never references `.webm`, but `apps/web/.gitignore` ignores
  `test-results/`, where Playwright writes video. The code is safe; the recording is not.
- **`map/pinEmphasis.ts` is binary to git.** Its `MATCHES_NOTHING` sentinel is a literal NUL byte,
  so the file diffs as `Bin 0 -> 5851 bytes` and cannot be reviewed. S5c replaces it first.

**Not claimed:** nothing here is verified against a live API or deployed to staging.
