# Access-lane commands — one screen

The five commands below are the Access lane's whole loop. They run under
`docs/adr/015-access-lane-self-merge.md`: an Access PR merges on green CI with no approval, once
`/gate` passes and `/audit` reports nothing blocking. Teammates review after the merge; an
objection becomes a follow-up PR.

ADR-015 covers the **Access lane only**. Root `CLAUDE.md` §6.0 — never push to `main`, never
merge your own PR — still binds Core and Signal. Never run `/ship` on their work.

## The loop, in order

**No command takes an argument.** All five default to the Access lane and read one queue,
`docs/status/access.md`. `/gate`, `/audit` and `/ship` work out the track from the diff —
`apps/api/**` → api, `apps/web/**` → web, both → both gates — and never ask.

| # | Command | What it does |
|---|---|---|
| 1 | `/brief` | Fetches, reads the lane's rules, lists the open PR and its CI, names stale status rows, then proposes **one** next item — its `[api]`/`[web]` tag, the branch it will create off `origin/main`, any unmerged local branch that already carries part of it, and whether it needs PRD detail. Stops. |
| 2 | `/gate` | Pre-flight on the current branch: size ≤ 400 lines, every changed path inside the ADR-008 Access paths, commitlint on every commit and the PR title, the gate commands the diff selected, and the PR body. Ends with `ready to /ship` or a numbered list of failures. |
| 3 | `/audit` | The review that stands in for a human approval. Reads the diff as the reviewer against merged ADRs, the work pack row, the Bible §§ it cites, `docs/CONTEXT.md` and `apps/web/CLAUDE.md`, and quotes back every overclaim. Task ID comes from the `Refs:` footers. |
| 4 | `/ship` | Pushes, opens the PR, watches CI, squash-merges when green, then returns to a detached `origin/main`. Refuses on `main`, on a failing gate, on a non-empty `/audit` blocking list, or on a path outside the Access lane. |
| 5 | `/wrap` | Rewrites `docs/status/access.md` from real state — real anchor sha, real PR numbers, real check results — and prints one block to paste. |

`/brief` → one "go" starts the work. `/gate` never asks whether to push; `/ship` does the pushing.
Anything blocking in `/audit` is fixed and `/audit` re-run before `/ship`.

## One queue

`docs/status/access.md` replaced `api.md` and `web.md`. Every row carries an `[api]` or `[web]`
tag, and the order is: an item that unblocks the other track first, otherwise alternate the two
tracks, and **never two open PRs at once**. One track running out of items means the rest of the
queue is the other track — that is the rule working, not a breach of it.

## Khillon's commands — do not use

`/resume`, `/checkpoint`, `/task` and `/handoff` are Khillon's (`bf38736`). They read
`.claude/state/progress-khillon.md` and grep `docs/workpacks/khillon.md`, so they report the wrong
lane's state. Two need a change; both are proposed as diffs on the PR that added this file and are
his to apply, not mine.

- **`/resume` shadows the built-in `/resume`. Use `/brief`.**
- `/task` step 4 cites `CLAUDE.md` §3 for lane boundaries, which grants Access all of
  `apps/api` Http. ADR-008 narrows it — see the standing rulings below.

## Lane boundary — the one that actually binds

ADR-008, not `CLAUDE.md` §3. Access owns these named paths in `apps/api`, and no others:
`app/Http/Controllers/Api/V1`, `app/Http/Resources`, `app/Http/Requests`, `routes/api.php`,
`app/Tiles`, `app/Lens`, `app/Broadcasting` + `routes/channels.php`, `app/Observations`, and
OpenAPI generation. `app/Http/Controllers/Auth/**`, `app/Http/Controllers/Findings/**` and
`app/Http/Middleware/**` are **Core's**. All of `apps/web` is Access's alone.

## Standing rulings — cross-lane items `/gate` and `/ship` may let through

Each of these is a small separate PR that touches another lane's file. Do it, FYI the owner in the
PR body, and never change their logic. `/gate` step 2 and `/ship` step 1 read this list.

| Item | Path | Owner to FYI |
|---|---|---|
| The `dedoc/scramble` dependency line | `apps/api/composer.json` | @khillon-makwana |
| Thin wrappers only | `apps/api/app/Console/Commands` | @khillon-makwana |
| Migration M2 — the `colour_class` CHECK on `lens.lens_scores`, with a real `down()` | `apps/api/database/migrations` | @khillon-makwana |
| Contract calls: S2 — `p` only where a weighted variable is provisional (DEC-15), so a failed gate is `ca` and coverage < 1 alone adds nothing; plus the DEC-20 display names | `docs/contracts/*` (Access's own file, but it settles a three-way source conflict) | @DevJ2005 · @khillon-makwana |

A decision that belongs to someone else and comes with a defensible recommendation is applied,
logged in `docs/status/access.md` under `## Decided without review — <owner> may object`, and the
work continues. Only a decision with no recommendation stops the session.

## PRD-001

Deliberately not in the repo, by Austine's choice. The sources are merged ADRs,
`docs/BUILD_BIBLE.md`, `docs/CONTEXT.md` and `apps/web/CLAUDE.md`. A claim that would rest on the
PRD is reported as "unverifiable — ask Austine" — never blocking, never guessed. When a task needs
PRD detail, name the excerpt and Austine pastes it.

## Permissions

`.claude/settings.local.json` is personal and gitignored — it is not the shared `settings.json`.
It pre-approves the read-only git and `gh` calls these commands make, plus `npx commitlint`, the
`npm run` scripts and the `vendor/bin` tools, so the loop runs without a prompt per step.

It denies `git push --force`, `git push -f`, `git reset --hard` and any deletion of `main`. The
deny rules match by prefix, so `--force-with-lease` is denied too: after a rebase, run that push
yourself with `! git push --force-with-lease`.
