# Status — Access lane (one queue)

Anchor: main @ `dc81cfc` · latest PR seen #117

One file, one queue. This replaced `docs/status/api.md` and `docs/status/web.md`, which never
reached `main` — they existed only on `docs/status-austine-lane`, so there was nothing to delete.
Every row below carries an `[api]` or `[web]` tag; the commands take no argument and read this file
(`docs/status/COMMANDS.md`).

## Open PR (one at a time)

**None.** `gh pr list --state open` returns empty. The queue rule — never two open PRs at once —
holds, and the next item may start.

## Merged since last update

The previous anchor was `5d98b6d`. Three PRs merged since it.

| PR | Track | Task IDs | One line |
|---|---|---|---|
| #117 | `[web]` | A-02 (part) | `detected` added to the finding states; `contested` no longer described as viewer-visible; `tiles.md` limiter, 429 body and §9 ownership corrected. |
| #116 | `[web]` | docs | The two Access status files merged into this one queue. |
| #115 | `[web]` | the command loop, ADR-015 | The five no-argument Access commands. Hand-gated, as ADR-015's Consequences records. |

Already absorbed before the old anchor, kept so the done-set is not lost: #114, #113 (B1b),
#112 (A1), #95, #94 (B1a), #93 (A0), #74, #73. So **A0, A1, B1a and B1b are done.**

Two Core merges still shape what this lane must do:

- **#88 `MODULES_ENABLED` / `app/Engine/ModuleFlags.php`** — a module can be switched off
  server-side. `GET /entities?module=` and `GET /lenses` must read the flag rather than list every
  module unconditionally, so a disabled module cannot be requested through the API. Lands in A6
  and A8.
- **#91 CORS is switched off** (`apps/api/config/cors.php`). Nothing changes for A2–A11 — the
  browser reaches the API through the same-origin dev proxy from #69 — but no endpoint of ours may
  be reached cross-origin, and no PR of ours re-enables CORS to work around a proxy gap.

## In progress

**Nothing open.** #117 landed and its branch is deleted both locally and on the remote.

**A-02 is part-merged, not done.** #117 was the description-only slice. Three pieces remain and
are row 1 of the queue below:

- **C1** — adopt the engine's statuses `scored · cannot_assess · provisional`; `labels.ts` still
  reads `partly_verified`. Measured at 11 call sites across 8 files.
- **S1** — the storage `entity_type` vocabulary (`point · parcel · segment · area`) plus `module`.
  Measured at 21 call sites across 8 files.
- **S2** — apply the ruling to `tiles.md` §5, replacing the live bullet with the quoted
  `lenses/README.md` rule. #117 recorded it as a proposal under Devyan's name rather than applying
  it.

C1 and S1 were split out of #117 deliberately: either one alone would have carried the PR past the
400-line cap.

## Next, in order

A2 and A4a lead because both unblock the web track. After that the two tracks alternate, each
keeping its own approved order. **Never two open PRs at once.** The tail is all `[web]` because the
api track runs out of items first — that is the rule working, not a breach of the alternation.
Numbering is unchanged from the previous revision; row 1 is narrowed, not dropped.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[api]` | **A2 remainder** — C1, S1 and applying the S2 ruling to `tiles.md` §5. The descriptions, `detected` and the tile-contract fixes merged as #117. Unblocks B4 and B5a | A-02 |
| 2 | `[api]` | A4a — fixtures compute coverage exactly as the engine does, weights from `weights.yml`, so fixture mode and the seeded database agree from the start (C11) | A-08 pre-req |
| 3 | `[web]` | B1c — Playwright visual-regression harness + committed baselines. Part-written, see the branch table | design |
| 4 | `[api]` | A3 — A-07a/b scored resources; the base resource throws on a score without coverage + confidence | A-07 |
| 5 | `[web]` | B2a — Button / Badge / Notice / Card | design |
| 6 | `[api]` | A4b — `SyntheticDemoSeeder` + `fixtures:export` | A-08 pre-req |
| 7 | `[web]` | B2b — SegmentedControl / ToggleGroup. **Partly written on `feat/web-demo`** — triage first | design |
| 8 | `[api]` | A5 — `GET /entities/{id}`, `/entities/{id}/scores` | A-08 |
| 9 | `[web]` | B2c — Tabs / Tooltip. **Partly written on `feat/web-demo`** — triage first | design |
| 10 | `[api]` | A6 — `GET /entities` (bbox required, ≤500), `/entities/{id}/flags`, `/lenses`; held-finding invisibility tests on every path | A-08, A-19 |
| 11 | `[web]` | B3a — shell + header + banner + footer. **Partly written on `feat/web-demo`** — triage first | A-12 |
| 12 | `[api]` | A7 — Scramble at `/api/v1/openapi.json`; `gen:api` reads it, committed snapshot as the CI fallback | A-06 |
| 13 | `[web]` | B3b — left rail: lens, filters, legend. No entity list. **Partly written on `feat/web-demo`, which also adds a place register the design ruling says not to build** | A-12 |
| 14 | `[api]` | A8 — `lens:sync` validation, `LensApplier`, `lens:apply --all` | A-11 |
| 15 | `[web]` | B3c — URL state: lens, filters, centre, zoom, selected entity | A-14 |
| 16 | `[api]` | A9 — MVT exactly per `tiles.md` + parity test against the frontend. `routes/api.php` has no tile route yet; the `tiles` limiter is already merged | A-09 |
| 17 | `[web]` | B4 — Passport restyle, visuals only; adds the "what would improve this" tip on not-measured rows, built from `null_reason`. Needs A2's C1 | A-16, A-17 |
| 18 | `[api]` | A10 — `VITE_DATA_SOURCE=api` switch + the `synthetic` flag | A-08 follow-up |
| 19 | `[web]` | B5a — finding detail; composes its sentences from `declared`/`observed` + `src/copy`. Needs A2's C7 | A-18 |
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
PRD is reported as "unverifiable — ask Austine" — never blocking, never guessed. When a task needs
PRD detail, name the excerpt and Austine pastes it.

## Decided without review — Devyan may object

Applied with a defensible recommendation and logged here, per ADR-015. An objection is a follow-up
PR, not a rollback. An entry leaves only when the owner answers.

- **`detected` has a display label, though `docs/CONTEXT.md` gives it none.** Merged in #117.
  CONTEXT.md § 4 lists `detected` as "— (internal, never shown)", i.e. deliberately no display
  word. `apps/web/src/copy/labels.ts` now gives it `'Just detected (analysts only)'` so
  `FINDING_STATUS` is total over the contract's seven states and `FindingDetail` cannot render
  `undefined`. The alternative was a partial record — the exact hole #117 existed to close.
  It is analyst-only copy for a state `FlagState`'s own docblock calls transient ("moved to held
  straight away"), so no viewer can reach it. One line in `labels.ts` if Devyan wants other words.
- **DEC-20 no longer blocks B3a.** The entity panel is **"Passport"**. Variable names come from
  `docs/CONTEXT.md` verbatim — Activity · Record vs reality · Momentum · Resource security ·
  Access — with CONTEXT.md's question as the tooltip, not as the label. The header reads
  **"Navuuna"**.
  Already true in code: `apps/web/src/copy/labels.ts` holds `PANEL_NAME`, `VARIABLE_LABELS` and
  `VARIABLE_TOOLTIPS`, and `labels.test.ts` pins all three, so a change of mind is a one-file
  change. Devyan owns `docs/CONTEXT.md`; this takes its words rather than inventing any.

## Unmerged local branches

This repo squash-merges, so `git branch --no-merged` lists merged work too. Only these carry
content that is not on `main`. Four live worktrees exist — `navuuna` (main), `navuuna-api` and
`navuuna-web` (both detached at `5d98b6d`), and `navuuna-demo` (on `feat/web-demo`).

| Branch | Head | What it is | What moves it |
|---|---|---|---|
| `feat/web-demo` | `0c07c15` | **Unrecorded until now, and the largest.** 3 commits, +1044/−85 across 24 files, no remote, checked out in the `navuuna-demo` worktree. Reference shell (header, tab bar, banner pill, page grid), map controls row, layer toggles, place register, neutral basemap palette, map selection state. Adds `TabBar.tsx`, `SegmentedControl.tsx`, `MapControls.tsx`, `MapLegend.tsx`, `PlaceRegister.tsx`, `BrandMark.tsx`. | Triage against queue rows 7, 9, 11 and 13 before any of them starts. Two conflicts to settle: `PlaceRegister.tsx` is an entity-register rail, which the design ruling below says was deliberately **not** built this round; and the work predates B2a, so its components may not sit on the B2a primitives. |
| `test/web-visual-regression-harness` | `3078c36` | B1c work — harness page, token-name test, four baselines. +671 lines, no remote. | Queue row 3. Rebase on `dc81cfc` first. |
| `fix/web-scale-fallback-and-line-styles` | `e164bba` | **Still unverified.** Looks superseded by #66 and #73, but shows unique content in `map/style/layers.ts` and `fixtures/generate.ts`. | Diff it against `dc81cfc` and either salvage or delete. Unchanged from the last revision. |
| `docs/contract-detected-state-and-tiles-fixes` | `a3741d5` | **Superseded by #117 — safe to delete.** It was the A2 partial. #117 is a strict improvement on it: `a3741d5` guards `FindingDetail` and `publicFindingStates.test.ts` with hand-kept arrays, which #117 replaced with a `Record` over the generated union. Merging it now would regress those two guards to fail-open. | `git branch -D docs/contract-detected-state-and-tiles-fixes` |

Safe to prune, all now merged or empty: `chore/claude-access-commands` (`aafa07f`, #115),
`docs/status-access-one-queue` (`739359d`, #116), `docs/status-austine-lane` (`6eaee6c`, superseded
by this file), and the six `worktree-agent-*` branches — only `…ae149f4255ea86115` carries a commit
ahead of `main` (`95ef18d`), and that merged as #93.

**Three untracked files are deliberately not committed**, so they are recorded here instead of
being lost: `apps/web/DEMO-CHEAT-SHEET.md` and `apps/web/scripts/demo-cheat-sheet-ids.mjs` both
declare themselves scratch in their own headers ("Not committed" / "Not shipped"), and the cheat
sheet is stale — it names `origin/main` as #68 `6110a74`. `.pr-bodies/` holds PR-body drafts.
None is in `.gitignore`, so all three show in every `git status`; adding them to it is a one-line
PR nobody has raised.

## Contract changes between the tracks

#117 landed the description-only slice. What is left is row 1 of the queue.

**Merged in #117:**

- **C10 — done.** `openapi.draft.yaml` described `contested` as "Still visible to viewers; the UI
  shows the challenge copy." That sentence contradicted ADR-013 and `Flag::PUBLIC_STATES`
  (`[published, resolved]`) and is where the viewer-visible contested fixture in #93 came from.
  Replaced, not softened. `schema.d.ts` regenerated.
- **C10b — done.** `FindingState` had six states; `app/Findings/FlagState.php` has seven.
  `detected` now has a label, a pre-publication banner, and an entry in the fixtures' own union.
  Both the `FindingDetail` banner map and the test's state list are now a `Record` over
  `components['schemas']['FindingSummary']['state']`, so an eighth contract state fails to compile
  rather than rendering with no banner.
- **`tiles.md` §7 — done.** The tile limiter is no longer TBD: `tiles` is merged in
  `AuthServiceProvider` as `TILE_REQUESTS_PER_MINUTE = 600`, ten times the API bucket, counted per
  signed-in user. The 429 body is the shared `App\Auth\TooManyRequestsResponse`
  (`{message, limit, window_seconds, retry_after_seconds}`) with `Retry-After`, `X-RateLimit-Limit`
  and `X-RateLimit-Remaining`. 600 stands until someone measures a real map load; K-18 times the
  tile SQL rather than counting requests and lists A-09 under **Needs**, so it cannot settle it.
- **`tiles.md` §9 — done.** It read "Until K-10 ships the real tile endpoint". ADR-005 "Ownership
  moved" reassigns MVT to Access as A-09 and explicitly asked for this correction in the contract
  PR. Corrected, with the citation.

**Still to apply, in the A2 remainder:**

- **S1 `entity_type` — ruled, not applied.** The API serves the storage vocabulary `point · parcel
  · segment · area` plus `module`, as Bible §6.1 and §11 define (`GET /entities?module=&type=`).
  The client composes display names in `src/copy/labels.ts`. Wards are `entity_type=area`; their
  module is read from what Devyan's loader writes — **not invented here**.
- **S2 `p` suffix — ruled, recorded as a proposal in `tiles.md`, not applied.** `p` marks a
  weighted variable that is **provisional** (its gate was unmeasured) and nothing else: a failed
  gate is `ca`, because a failed gate makes the variable `cannot_assess`
  (`GateState::Failed` → `VariableStatus::CannotAssess`, and the
  `variable_scores_gate_matches_status_check` CHECK), and coverage below 1 alone adds nothing —
  coverage shows through "X of Y" beside the score (DEC-15). `lenses/README.md` already says this
  and matches the merged engine, so **`tiles.md` §5 is the side that moves.** #117 quotes that rule
  verbatim and names the two files that actually decide `p` today —
  `fixtures/generate.ts:421` and `vite-plugins/mockServer.ts:69`, not `lensApply.ts`, which only
  receives the flag.
- **C1 — ruled, not applied.** Adopt the engine's statuses `scored · cannot_assess · provisional`.
  The "Partly verified" badge means **provisional only**; coverage below 1 shows through "X of Y",
  never through the badge. `labels.ts` still reads `partly_verified`, kept pending this. Touches
  `fixtures/types.ts`, `panel/VariableRow.tsx` and `map/style/scale.ts`; the last lands in B4.
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

## Gate

Only commands that actually ran, with their real results.

- **#117 on `1981535`, before the push:** `git diff --stat origin/main...HEAD` → 225 changed lines
  (178+/47−, 10 files), under the 400 cap · `npx commitlint --from origin/main --to HEAD --verbose`
  → 0 problems, 0 warnings on all three commits · the PR title linted the same way → 0 problems ·
  `npm run typecheck` → clean · `npm run lint` → clean (eslint + prettier) · `npm run test` →
  123 passed / 18 files · `npm run build` → built · `npm run gen:api` then
  `git diff -- src/api/schema.d.ts` → **empty**, so `schema.d.ts` is the generator's output and
  `apps/web` rule 7 is verified rather than asserted.
- **#117 claims checked against merged source, not a running API:** `FlagState.php` has seven
  cases including `Detected`; `Flag::PUBLIC_STATES` is `[Published, Resolved]`;
  `TILE_REQUESTS_PER_MINUTE = 600` is in `AuthServiceProvider`; `TooManyRequestsResponse` emits
  those four keys; the `lenses/README.md` blockquote in `tiles.md` is byte-exact; ADR-005 lines
  57–60 ask for the §9 correction; `routes/api.php` still has no tile route.
- **#117 CI: 9 checks, all green** — commitlint, dependency-audit, flows, migration-lint,
  modularity, php, python, secret-scan, web. The count is 9, not the 8 recorded last revision.
- **#117 merged** as squash commit `dc81cfc`; branch deleted locally and on the remote.
- `gh pr list --state open` → empty.

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

- **Entity register rail:** not built this round. The A-12 rail is lens + filters + legend only.
  Leak rule: any future entity list reads `GET /entities`, which never carries finding state, and
  shows a viewer no finding indicator of any kind. **`feat/web-demo` adds a `PlaceRegister.tsx`
  against this ruling** — triage it before B3b.
- **Dark theme:** recorded, not built. Thirty hex values in `DESIGN.md` §1.2; light theme only.
- **Raw-response `<details>`:** adopted but backlogged until A10, and then it renders the exact JSON
  the client fetched — no client-side composition.
- **Source chips** yes; the five-dot confidence glyph no.
- **`--line`, `--water`, `--new` and the notice colour** carry no status meaning. Text ≥ 4.5:1,
  UI ≥ 3:1. Red is reserved for system errors — never a score, never a finding.
