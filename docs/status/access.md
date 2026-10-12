# Status — Access lane (one queue)

Anchor: main @ `5d98b6d` · latest PR seen #115

One file, one queue. This replaced `docs/status/api.md` and `docs/status/web.md`, which never
reached `main` — they existed only on `docs/status-austine-lane`, so there was nothing to delete.
Every row below carries an `[api]` or `[web]` tag; the commands take no argument and read this file
(`docs/status/COMMANDS.md`).

## Open PR (one at a time)

| PR | Task IDs | State | Waiting on |
|---|---|---|---|
| #115 | the command loop, ADR-015 | MERGEABLE, CI green. 386 changed lines | Austine merges it on GitHub |

#115 is hand-gated, not shipped by `/ship`: `.claude/`, `.gitignore` and `docs/status/` have no
CODEOWNERS entry and `docs/adr/` routes to all three owners, so `/ship` step 1 would refuse its own
introduction. ADR-015's Consequences section records that bootstrap exception.

## Merged since last update

Everything the old status files listed as open has merged. Verified with `gh pr view`, not assumed.

| PR | Task IDs | One line |
|---|---|---|
| #114 | B1b-ii (design) | Self-hosted OFL woff2 subsets and base typography. |
| #113 | B1b-i (design) | `b1`–`b4` score ramp, Tailwind v4 `@theme` tokens, re-runnable contrast script. |
| #112 | A1 (A-10, DEC-13) | `GET /api/v1/me` — identity and capability, session only. |
| #95 | B1a-ii (design) | `DESIGN-mapping.md` — what we take from the reference and what we change. |
| #94 | B1a-i (design) | `DESIGN.md` + the `docs/design/reference/` ignore rule. |
| #93 | A0 (A-08, ADR-013) | Every non-public finding hidden from viewers, in the fixtures and the mock. |
| #74 | A-26 | Dates formatted "14 Sep 2026" in Africa/Nairobi. |
| #73 | A-13 | Partly-verified and cannot-assess roads given their own layers. |

So **A0, A1, B1a and B1b are done**, and the queue starts at A2 and B1c.

Two Core merges still shape what this lane must do:

- **#88 `MODULES_ENABLED` / `app/Engine/ModuleFlags.php`** — a module can be switched off
  server-side. `GET /entities?module=` and `GET /lenses` must read the flag rather than list every
  module unconditionally, so a disabled module cannot be requested through the API. Lands in A6
  and A8.
- **#91 CORS is switched off** (`apps/api/config/cors.php`). Nothing changes for A2–A11 — the
  browser reaches the API through the same-origin dev proxy from #69 — but no endpoint of ours may
  be reached cross-origin, and no PR of ours re-enables CORS to work around a proxy gap.

## In progress

A2 is part-written on `docs/contract-detected-state-and-tiles-fixes` @ `a3741d5`, local and
unpushed. It is cut from current `main`, so it needs no rebase.

It carries C10, C10b, the `EntityDetail.findings` description, the `tiles.md` 429 body and the
ADR-005 ownership correction, plus a real `PRE_PUBLICATION_STATES` fix so a `detected` finding wears
the "Not visible to other users until published" banner. **It is a subset of A2:** C1, S1 and the
S2 ruling are not in it yet and still have to land in the A2 PR.

A duplicate branch, `fix/contract-finding-states-and-tiles` (`9b29831`), has been deleted. It held
the better contract content but was cut before #112, so merging it would have reverted the `/me`
contract. Its content was carried onto the kept branch first; 47 web tests and `tsc --noEmit` pass
there.

## Next, in order

A2 and A4a lead because both unblock the web track. After that the two tracks alternate, each
keeping its own approved order. **Never two open PRs at once.** The tail is all `[web]` because the
api track runs out of items first — that is the rule working, not a breach of the alternation.

| # | Track | What | Task IDs |
|---|---|---|---|
| 1 | `[api]` | A2 — contract PR: C1–C13, the S1/S2 rulings, C10 + C10b. Unblocks B4 and B5a | A-02 |
| 2 | `[api]` | A4a — fixtures compute coverage exactly as the engine does, weights from `weights.yml`, so fixture mode and the seeded database agree from the start (C11) | A-08 pre-req |
| 3 | `[web]` | B1c — Playwright visual-regression harness + committed baselines | design |
| 4 | `[api]` | A3 — A-07a/b scored resources; the base resource throws on a score without coverage + confidence | A-07 |
| 5 | `[web]` | B2a — Button / Badge / Notice / Card | design |
| 6 | `[api]` | A4b — `SyntheticDemoSeeder` + `fixtures:export` | A-08 pre-req |
| 7 | `[web]` | B2b — SegmentedControl / ToggleGroup | design |
| 8 | `[api]` | A5 — `GET /entities/{id}`, `/entities/{id}/scores` | A-08 |
| 9 | `[web]` | B2c — Tabs / Tooltip | design |
| 10 | `[api]` | A6 — `GET /entities` (bbox required, ≤500), `/entities/{id}/flags`, `/lenses`; held-finding invisibility tests on every path | A-08, A-19 |
| 11 | `[web]` | B3a — shell + header + banner + footer. No longer blocked, see the ruling below | A-12 |
| 12 | `[api]` | A7 — Scramble at `/api/v1/openapi.json`; `gen:api` reads it, committed snapshot as the CI fallback | A-06 |
| 13 | `[web]` | B3b — left rail: lens, filters, legend. No entity list | A-12 |
| 14 | `[api]` | A8 — `lens:sync` validation, `LensApplier`, `lens:apply --all` | A-11 |
| 15 | `[web]` | B3c — URL state: lens, filters, centre, zoom, selected entity | A-14 |
| 16 | `[api]` | A9 — MVT exactly per `tiles.md` + parity test against the frontend | A-09 |
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
PR, not a rollback.

- **DEC-20 no longer blocks B3a.** The entity panel is **"Passport"**. Variable names come from
  `docs/CONTEXT.md` verbatim — Activity · Record vs reality · Momentum · Resource security ·
  Access — with CONTEXT.md's question as the tooltip, not as the label. The header reads
  **"Navuuna"**.
  Already true in code: `apps/web/src/copy/labels.ts` holds `PANEL_NAME`, `VARIABLE_LABELS` and
  `VARIABLE_TOOLTIPS`, and `labels.test.ts` pins all three, so a change of mind is a one-file
  change. Devyan owns `docs/CONTEXT.md`; this takes its words rather than inventing any.

## Contract changes between the tracks

Everything here lands in **A2**, the contract PR. Reviewers Khillon + Devyan as FYI.

- **S1 `entity_type` — ruled.** The API serves the storage vocabulary `point · parcel · segment ·
  area` plus `module`, as Bible §6.1 and §11 already define (`GET /entities?module=&type=`). The
  client composes display names in `src/copy/labels.ts`. Wards are `entity_type=area`; their module
  is read from what Devyan's loader writes — **not invented here**. Not yet applied on the A2 branch.
- **S2 `p` suffix — ruled, still to apply.** `p` marks a weighted variable that is **provisional**
  (its gate was unmeasured) and nothing else: a failed gate is `ca`, because a failed gate makes the
  variable `cannot_assess` (`GateState::Failed` → `VariableStatus::CannotAssess`, and the
  `variable_scores_gate_matches_status_check` CHECK), and coverage below 1 alone adds nothing —
  coverage shows through "X of Y" beside the score (DEC-15). `lenses/README.md` already says this
  and matches the merged engine, so **`tiles.md` §5 is the side that moves.** Both A2 branches only
  *deferred* this to Devyan; the ruling has to be applied in the A2 PR.
- **C1 — ruled, still to apply.** Adopt the engine's statuses `scored · cannot_assess ·
  provisional`. The "Partly verified" badge means **provisional only**; coverage below 1 shows
  through "X of Y", never through the badge. `labels.ts` still reads `partly_verified`, kept
  pending this. Touches `fixtures/types.ts`, `panel/VariableRow.tsx` and `map/style/scale.ts`, which
  lands in B4, not A2.
- **C10 — fixed on the A2 branch.** `openapi.draft.yaml` described `contested` as "Still visible to
  viewers; the UI shows the challenge copy." That sentence is where C10 came from and it contradicts
  ADR-013 and `Flag::PUBLIC_STATES`. #93 fixed the fixtures; the A2 branch fixes the sentence.
  `schema.d.ts` on `main` still carries it.
- **C10b — fixed on the A2 branch.** `FindingState` in the contract had six states; the API's
  `FlagState` has seven. `detected` was the missing one, and it now has a label and a banner.
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
- **To Core:** `tiles.md` §9 said "Until K-10 ships the real tile endpoint" — ADR-008 moved MVT to
  Access (A-09). Corrected on the A2 branch, citing ADR-005 "Ownership moved".
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

- **Entity register rail:** not built this round. The A-12 rail is lens + filters + legend only.
  Leak rule: any future entity list reads `GET /entities`, which never carries finding state, and
  shows a viewer no finding indicator of any kind.
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
| `chore/claude-access-commands` | `aafa07f` | PR #115, open. |
| `docs/contract-detected-state-and-tiles-fixes` | `a3741d5` | The kept A2 partial. On current `main`. |
| `test/web-visual-regression-harness` | `3078c36` | B1c work — harness page, token-name test, four baselines. +671 lines. |
| `fix/web-scale-fallback-and-line-styles` | `e164bba` | **Unverified.** Looks superseded by #66 and #73, but still shows unique content in `map/style/layers.ts` and `fixtures/generate.ts`. Check before deleting. |

`docs/status-austine-lane` (`6eaee6c`) is superseded by this file and can be abandoned. The rest of
the local branches correspond to merged PRs and are safe to prune.

## Gate

- #115 — `npx commitlint --from origin/main --to HEAD --verbose` → 0 problems, 0 warnings;
  `git diff --stat origin/main...HEAD` → 386 changed lines, under the 400 cap; MERGEABLE
- A2 branch `a3741d5` — `npx vitest run src/findings src/copy` → 47 passed / 4 files;
  `npx tsc --noEmit` → clean. Not pushed, so no CI run yet
- `gh pr view` on #73, #74, #93, #94, #95, #112, #113, #114 → all MERGED
- The check count is 8, not 7 — `dependency-audit` joined in Core's #91
