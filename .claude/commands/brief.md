---
description: Open an Access-lane session — state, open PRs, CI, and the single next item
---
No arguments. Always the Access lane, always `docs/status/access.md`, always one next item.

1. `git fetch --all --prune`, then `git status -sb`, `git branch --show-current`,
   `git log --oneline -10` and `git rev-list --left-right --count origin/main...HEAD`.
   Report ahead/behind — a stale tree is the usual cause of a wrong answer here.
2. Read root `CLAUDE.md` §3–§6 and `apps/web/CLAUDE.md`. Read the newest merged ADR that names the
   Access lane: `CLAUDE.md` §1 puts merged ADRs above `CLAUDE.md` and the Bible.
3. Read `docs/status/access.md` — one file, one queue, every row tagged `[api]` or `[web]`. Missing
   → say so; never invent one. Do not read the Bible or `docs/workpacks/austine.md` body yet.
4. `gh pr list --state open --json number,title,headRefName,mergeable`, then `gh pr checks <n>` for
   each. Compare with the file's "Open PRs" table and name the rows that are now stale — the file
   is a snapshot, `gh` is the truth.
5. Propose the **first unblocked row** of "Next, in order" — one item, not a menu. If the file calls
   it in progress but `gh` says it merged, propose the row after it. Give:
   - its `[api]` or `[web]` tag, and which gate commands that implies
   - the **branch name** it will create, `type/scope-short-description`, branched from
     `origin/main` — not from the current HEAD
   - whether an unmerged local branch already carries some of it, with the sha
   - whether it needs **PRD detail** (screen states, copy, review-screen behaviour, edge cases
     E1–E15). `docs/PRD.md` is deliberately out of the repo, so name the excerpt needed and stop
     short of guessing it. Austine pastes it.
6. A PR is already open → say so and stop there. One open PR at a time is the queue rule; starting
   a second item is not a shortcut, it is the thing the rule forbids.
7. Reply in at most 12 lines: branch + ahead/behind · stale status rows · the open PR and its check
   state · still-open items under "Blocked / needs a decision" · unmerged local branches worth
   knowing about · the proposed next item with its task ID, tag and branch name · the PRD excerpt
   needed, if any. Then STOP. One "go" starts the work; do not start it here and do not explore the
   codebase.
