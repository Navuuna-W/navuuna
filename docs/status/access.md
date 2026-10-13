# Status — Access lane (one queue)

Anchor: main @ `dc81cfc` · latest PR seen #119 (Devyan's, Signal lane)

## Standing rules

1. **A status update never gets its own PR.** `/wrap` commits this file onto the next queue item's
   branch, cut from `origin/main`, and `/ship` carries it there. If a status-only PR is ever open,
   close it rather than iterate on it. #118 was closed unmerged under this rule; its content is this
   file, now riding on row 1's branch.
2. **Keep this file short.** No commentary about which sha or which run is pinned — live values come
   from `gh pr view` and `gh pr checks`.
3. **The place register is allowed**, with entity data only, no finding indicator of any kind, and
   `PlaceRegister.leak.test.tsx` required. Settled, not an open question.
4. **`feat/web-demo` is untouched until after the demo.** Then land C1 first and salvage the branch
   by cherry-pick into ≤ 400-line PRs. Never merge it whole — it predates #117.

One file, one queue. It replaced `docs/status/api.md` and `docs/status/web.md`, which never reached
`main`. Every row below carries an `[api]` or `[web]` tag; the commands take no argument and read
this file (`docs/status/COMMANDS.md`).

## Open PR (one at a time)

**None in this lane.** Closing #118 frees row 1, which is the work on this branch
(`feat/web-a02-remainder-c1-s1`, cut from `dc81cfc`). Devyan's **#119** — ADR-016, the water
register — is open in the Signal lane and does not count against this lane's one-PR limit.

Merging is hand-gated: root `CLAUDE.md` §6.0 forbids an agent merging a PR, and ADR-015's
Access-lane carve-out does not reach the permission layer that reads it. Austine runs
`gh pr merge <n> --squash --delete-branch`. ADR-015's Consequences records this for #115.

ADR-016 touches this lane in one way, and changes no contract: the water register is hand-curated,
so coverage will be thin and more variables arrive `null_not_measured` / provisional. That raises
the value of B4's "what would improve this" tip, which is built from `null_reason`.

## Merged since last update

**Nothing has merged since the anchor.** `origin/main` is still `dc81cfc`.

The done-set, kept so it is not lost across revisions: #117 (A-02 part — `detected` added to the
finding states, `contested` no longer described as viewer-visible, `tiles.md` limiter, 429 body and
§9 ownership corrected), #116 (the two Access status files merged into this one queue), #115 (the
five no-argument Access commands, hand-gated per ADR-015), #114, #113 (B1b), #112 (A1), #95,
#94 (B1a), #93 (A0), #74, #73. So **A0, A1, B1a and B1b are done**, and A-02 is part-done.

Two Core merges still shape what this lane must do:

- **#88 `MODULES_ENABLED` / `app/Engine/ModuleFlags.php`** — a module can be switched off
  server-side. `GET /entities?module=` and `GET /lenses` must read the flag rather than list every
  module unconditionally, so a disabled module cannot be requested through the API. Lands in A6
  and A8.
- **#91 CORS is switched off** (`apps/api/config/cors.php`). Nothing changes for A2–A11 — the
  browser reaches the API through the same-origin dev proxy from #69 — but no endpoint of ours may
  be reached cross-origin, and no PR of ours re-enables CORS to work around a proxy gap.

## In progress

**Row 1 — the A-02 remainder — on `feat/web-a02-remainder-c1-s1`.** Only this status file is
committed so far. #117 was the description-only slice; three pieces remain:

- **C1** — adopt the engine's statuses `scored · cannot_assess · provisional`; `labels.ts` still
  reads `partly_verified`, which appears on 11 lines across 8 files
  (`git grep -n partly_verified -- apps/web`, on `dc81cfc`).
- **S1** — the storage `entity_type` vocabulary (`point · parcel · segment · area`) plus `module`.
  `entity_type` appears on 25 lines across 12 files
  (`git grep -n entity_type -- apps/web`, on `dc81cfc`). Both counts are a grep, not a count of
  edits the change needs.
- **S2** — apply the ruling to `tiles.md` §5, replacing the live bullet with the quoted
  `lenses/README.md` rule. #117 recorded it as a proposal under Devyan's name rather than applying
  it.

C1 and S1 were split out of #117 deliberately: either one alone would have carried that PR past the
400-line cap.

## Next, in order

A2 and A4a lead because both unblock the web track. After that the two tracks alternate, each
keeping its own approved order. **Never two open PRs at once.** The tail is all `[web]` because the
api track runs out of items first — that is the rule working, not a breach of the alternation.
Numbering is unchanged: nothing merged this round, so no row was dropped or renumbered.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[api]` | **A2 remainder** — C1, S1 and applying the S2 ruling to `tiles.md` §5. In progress on this branch. Unblocks B4 and B5a | A-02 |
| 2 | `[api]` | A4a — fixtures compute coverage exactly as the engine does, weights from `weights.yml`, so fixture mode and the seeded database agree from the start (C11) | A-08 pre-req |
| 3 | `[web]` | B1c — Playwright visual-regression harness + committed baselines. Part-written, see the branch table | design |
| 4 | `[api]` | A3 — A-07a/b scored resources; the base resource throws on a score without coverage + confidence | A-07 |
| 5 | `[web]` | B2a — Button / Badge / Notice / Card | design |
| 6 | `[api]` | A4b — `SyntheticDemoSeeder` + `fixtures:export` | A-08 pre-req |
| 7 | `[web]` | B2b — SegmentedControl / ToggleGroup. Cherry-pick candidate on `feat/web-demo` | design |
| 8 | `[api]` | A5 — `GET /entities/{id}`, `/entities/{id}/scores` | A-08 |
| 9 | `[web]` | B2c — Tabs / Tooltip. Cherry-pick candidate on `feat/web-demo` | design |
| 10 | `[api]` | A6 — `GET /entities` (bbox required, ≤500), `/entities/{id}/flags`, `/lenses`; held-finding invisibility tests on every path | A-08, A-19 |
| 11 | `[web]` | B3a — shell + header + banner + footer. Cherry-pick candidate on `feat/web-demo` | A-12 |
| 12 | `[api]` | A7 — Scramble at `/api/v1/openapi.json`; `gen:api` reads it, committed snapshot as the CI fallback | A-06 |
| 13 | `[web]` | B3b — left rail: lens, filters, legend, **plus the place register under rule 3** — entity data only, no finding indicator, leak test required. `PlaceRegister.tsx` + `PlaceRegister.leak.test.tsx` come off `feat/web-demo` | A-12 |
| 14 | `[api]` | A8 — `lens:sync` validation, `LensApplier`, `lens:apply --all` | A-11 |
| 15 | `[web]` | B3c — URL state: lens, filters, centre, zoom, selected entity | A-14 |
| 16 | `[api]` | A9 — MVT exactly per `tiles.md` + parity test against the frontend. `routes/api.php` has no tile route yet; the `tiles` limiter is already merged | A-09 |
| 17 | `[web]` | B4 — Passport restyle, visuals only; adds the "what would improve this" tip on not-measured rows, built from `null_reason`. Needs A2's C1. Largely written on `feat/web-demo` (`dd5c0a8`) — cherry-pick | A-16, A-17 |
| 18 | `[api]` | A10 — `VITE_DATA_SOURCE=api` switch + the `synthetic` flag | A-08 follow-up |
| 19 | `[web]` | B5a — finding detail; composes its sentences from `declared`/`observed` + `src/copy`. Needs A2's C7. Part-written on `feat/web-demo` (`c044fae`), which rewrites the files #117 fixed — cherry-pick after C1, never merge | A-18 |
| 20 | `[api]` | A11 — broadcast channels. Last, after A2–A10 merge | A-21 |
| 21 | `[web]` | B5b — states matrix + tile-failure retry | A-24 |
| 22 | `[web]` | B6 — sign-in S7. Unblocked: `GET /me` merged as #112 | A-10 |
| 23 | `[web]` | B7a — review queue S4 | A-22 |
| 24 | `[web]` | B7b — review actions S5; buildable against the real `/flags`, 404-not-403 behaviour kept | A-22 |
| 25 | `[web]` | B8a — accessibility | A-25 |
| 26 | `[web]` | B8b — 390 px | A-25 |

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
PRD is reported as "unverifiable — ask Austine" — never blocking, never guessed.

## Decided without review — Devyan may object

Applied with a defensible recommendation and logged here, per ADR-015. An objection is a follow-up
PR, not a rollback. An entry leaves only when the owner answers.

- **`detected` has a display label, though `docs/CONTEXT.md` gives it none.** Merged in #117.
  CONTEXT.md § 4 lists `detected` as "— (internal, never shown)". `apps/web/src/copy/labels.ts` now
  gives it `'Just detected (analysts only)'` so `FINDING_STATUS` is total over the contract's seven
  states and `FindingDetail` cannot render `undefined`. The alternative was a partial record — the
  exact hole #117 existed to close. It is analyst-only copy for a state `FlagState`'s own docblock
  calls transient, so no viewer can reach it. One line in `labels.ts` if Devyan wants other words.
- **DEC-20 no longer blocks B3a.** The entity panel is **"Passport"**. Variable names come from
  `docs/CONTEXT.md` verbatim — Activity · Record vs reality · Momentum · Resource security ·
  Access — with CONTEXT.md's question as the tooltip, not as the label. The header reads
  **"Navuuna"**. Already true in code: `labels.ts` holds `PANEL_NAME`, `VARIABLE_LABELS` and
  `VARIABLE_TOOLTIPS`, and `labels.test.ts` pins all three, so a change of mind is one file.

## Unmerged local branches

This repo squash-merges, so `git branch --no-merged` lists merged work too. Only these carry content
that is not on `main`. Re-read the worktree pairing with `git worktree list`; only
`navuuna-demo` ↔ `feat/web-demo` is stable.

| Branch | Head | What it is | What moves it |
|---|---|---|---|
| `feat/web-demo` | `c044fae` | The largest: 6 commits, +1716/−302 across 38 files, no remote, checked out in the `navuuna-demo` worktree. Merge-base with `main` is `886bb57` (#115), so **it does not contain #117**. Reference shell, map controls, layer toggles, place register, neutral basemap, map selection state; then `dd5c0a8` Passport on the reference's `.report` shape, `0e9687c` "measured" not "present" + `PlaceRegister.leak.test.tsx` (135 lines, mutation-verified), `c044fae` neutral finding cards, `isPublicFindingState` notice, analyst-preview notice on `RoleSwitch`, `NoticePill.tsx`. | **Hands off until after the demo (rule 4).** Then C1 lands first, and the branch is salvaged by cherry-pick onto `main` in ≤ 400-line PRs — rows 7, 9, 11, 13, 17, 19. Never merged whole: three measured regressions — `labels.ts` here has **no `detected` entry** (`dc81cfc:labels.ts:57` has it), so `FindingDetail` can render `undefined`; `publicFindingStates.test.ts` still lists states "by hand on purpose", the fail-open pattern #117 replaced with a `Record` over the generated union; and `partly_verified` is on **18** lines here against 11 on `dc81cfc`, so merging first would hand C1 seven more call sites. The wanted parts are the leak test, the neutral cards, the analyst-preview notice, the Passport restyle and — now that rule 3 allows it — the place register. |
| `test/web-visual-regression-harness` | `3078c36` | B1c work — harness page, token-name test, four baselines. +671 lines, no remote. Merge-base `5d98b6d`. | Queue row 3. Rebase on `main` first. |
| `fix/web-scale-fallback-and-line-styles` | `e164bba` | **Still unverified.** Looks superseded by #66 and #73, but shows unique content in `map/style/layers.ts` and `fixtures/generate.ts`. | Diff it against `main` and either salvage or delete. |
| `docs/contract-detected-state-and-tiles-fixes` | `a3741d5` | **Superseded by #117 — safe to delete.** It was the A2 partial; #117 is a strict improvement, replacing its hand-kept guard arrays with a `Record` over the generated union. | `git branch -D docs/contract-detected-state-and-tiles-fixes` |

Safe to prune, all merged, closed or empty: `docs/status-access-after-117` (#118, closed unmerged —
its content is this file, so nothing is lost), `chore/claude-access-commands` (#115),
`docs/status-access-one-queue` (#116), `docs/status-austine-lane` (superseded by this file), and the
six `worktree-agent-*` branches — only `…ae149f4255ea86115` carries a commit ahead of `main`
(`95ef18d`), and that merged as #93.

**Three untracked paths are deliberately not committed**, so they are recorded here instead of being
lost. `apps/web/DEMO-CHEAT-SHEET.md` says "Not committed — a one-off scratch note for the live
walkthrough" in its own line 3 and is measurably stale (it names `origin/main` as #68 `6110a74` and
demos from a branch whose remote is gone); `apps/web/scripts/demo-cheat-sheet-ids.mjs` line 2 reads
"Not shipped"; `.pr-bodies/` holds 20 PR-body drafts. `/wrap` step 3 says commit uncommitted work,
but committing self-declared scratch into a tracked path is worse than registering it here. None is
in `.gitignore`, so all three show in every `git status`; adding them is a one-line PR nobody has
raised.

## Contract changes between the tracks

#117 landed the description-only slice: **C10** (`openapi.draft.yaml` no longer calls `contested`
viewer-visible, contradicting ADR-013 and `Flag::PUBLIC_STATES`; `schema.d.ts` regenerated),
**C10b** (`detected` added, so `FindingState` matches `FlagState`'s seven cases, both the banner map
and the test's state list now a `Record` over the generated union), **`tiles.md` §7** (the limiter is
`TILE_REQUESTS_PER_MINUTE = 600` per signed-in user, 429 body
`{message, limit, window_seconds, retry_after_seconds}` with `Retry-After`, `X-RateLimit-Limit`,
`X-RateLimit-Remaining`) and **`tiles.md` §9** (MVT is Access's A-09 per ADR-005, not K-10's).

Still to apply, in the A2 remainder:

- **S1 `entity_type`** — the API serves the storage vocabulary `point · parcel · segment · area`
  plus `module`, per Bible §6.1 and §11 (`GET /entities?module=&type=`). The client composes display
  names in `src/copy/labels.ts`. Wards are `entity_type=area`; their module is read from what
  Devyan's loader writes — **not invented here**.
- **S2 `p` suffix** — `p` marks a weighted variable that is **provisional** (its gate was
  unmeasured) and nothing else. A failed gate is `ca` (`GateState::Failed` →
  `VariableStatus::CannotAssess`, enforced by the `variable_scores_gate_matches_status_check`
  CHECK), and coverage below 1 adds nothing — coverage shows through "X of Y" beside the score
  (DEC-15). `lenses/README.md` already says this and matches the merged engine, so **`tiles.md` §5
  is the side that moves.** The two files that actually decide `p` today are
  `fixtures/generate.ts:421` and `vite-plugins/mockServer.ts:69`, not `lensApply.ts`, which only
  receives the flag.
- **C1** — adopt `scored · cannot_assess · provisional`. The "Partly verified" badge means
  **provisional only**; coverage below 1 shows through "X of Y", never through the badge. Touches
  `fixtures/types.ts`, `panel/VariableRow.tsx` and `map/style/scale.ts`; the last lands in B4.
- **C11 — ruled into A4a**, not A10, so fixture mode and the seeded database never diverge: the
  engine's `coverage` is weight-weighted and the fixtures must weight it the same way.
- **C5, C7, C13, R4, R7 — accepted.** The R4 test pinning Core's `inputs` keys is required.
- **Seeder — option 3 approved.** `generate.ts` → committed `synthetic.json` →
  `SyntheticDemoSeeder` as a plain loader, with a CI diff. Synthetic flag is
  `metadata {"synthetic": true}` plus "(synthetic)" source names; the API's `synthetic` is true when
  **any** contributing source is synthetic. The seeder refuses to run outside local/testing. Rollup
  and lens scores come from running the real commands — never hand-written rows.
- **No `resolved` finding exists in the fixtures**, though a viewer will see that state. One line,
  but it moves the fixture counts, so it belongs with A4a's coverage rework.
- **To Signal:** C7 means findings carry `declared`/`observed`/`gap` + `narrative`, not pre-composed
  sentences; the gap wording stays in `docs/CONTEXT.md`. C13 drops `name`/`label` from the response
  — labels stay in `apps/web/src/copy`, so a DEC-20 change is a one-file change.

## Gate

Only commands that actually ran, with their real results. Live CI is `gh pr checks <n>`.

- **#117 before its push:** 225 changed lines (178+/47−, 10 files) · commitlint 0 problems on all
  three commits and on the title · `npm run typecheck`, `npm run lint`, `npm run build` clean ·
  `npm run test` 123 passed / 18 files · `npm run gen:api` then `git diff -- src/api/schema.d.ts`
  → **empty**, so `schema.d.ts` is the generator's output. CI 9 of 9 green; merged as `dc81cfc`.
- **#117's claims checked against merged source, not a running API:** `FlagState.php` has seven
  cases including `Detected`; `Flag::PUBLIC_STATES` is `[Published, Resolved]`;
  `TILE_REQUESTS_PER_MINUTE = 600` is in `AuthServiceProvider`; `TooManyRequestsResponse` emits
  those four keys; the `lenses/README.md` blockquote in `tiles.md` is byte-exact; ADR-005 lines
  57–60 ask for the §9 correction; `routes/api.php` still has no tile route.
- **This round:** `git fetch --all --prune` clean · `git rev-parse --short origin/main` → `dc81cfc`,
  unmoved · `gh pr list --state open` → #119 (Devyan's) and, before closing, #118 ·
  `gh pr view 118 --json files` → `docs/status/access.md` only, so it was status-only and rule 1
  applies · `gh pr close 118` done, unmerged · branch `feat/web-a02-remainder-c1-s1` cut from
  `origin/main`. The web and api gate commands were **correctly skipped**: the diff touches no
  `apps/api/**` and no `apps/web/**`.
- **Every #118 head ran 9 of 9 green** (commitlint, dependency-audit, flows, migration-lint,
  modularity, php, python, secret-scan, web) — the same nine this branch must pass.
- **The two A2 grep counts, on `dc81cfc`:** `partly_verified` → 11 lines / 8 files;
  `entity_type` → 25 lines / 12 files.
- **`feat/web-demo` measured, not assumed:** `git merge-base main feat/web-demo` → `886bb57`, so it
  predates #117 · 6 commits · +1716/−302, 38 files · `git grep -n detected` in its `labels.ts` →
  **no match** · its `publicFindingStates.test.ts` line 15 still reads "Listed by hand on purpose" ·
  `partly_verified` → 18 lines. That is the basis for rule 4.

**Not claimed:** nothing in this lane is verified against a live API or deployed to staging.
Everything above is fixtures, local commands and CI.

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

- **Place register: built, under rule 3.** Entity data only; it reads `GET /entities`, which never
  carries finding state, and shows a viewer no finding indicator of any kind — including never
  hiding a row, since absence is itself a signal. `PlaceRegister.leak.test.tsx` is required and
  already exists on `feat/web-demo` (mutation-verified; `readVisiblePlaces.ts` lost its
  finding-state read). It lands with B3b, row 13.
- **Dark theme:** recorded, not built. Thirty hex values in `DESIGN.md` §1.2; light theme only.
- **Raw-response `<details>`:** adopted but backlogged until A10, and then it renders the exact JSON
  the client fetched — no client-side composition.
- **Source chips** yes; the five-dot confidence glyph no.
- **`--line`, `--water`, `--new` and the notice colour** carry no status meaning. Text ≥ 4.5:1,
  UI ≥ 3:1. Red is reserved for system errors — never a score, never a finding.
