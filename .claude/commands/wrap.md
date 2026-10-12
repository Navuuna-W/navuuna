---
description: Rewrite the Access status file from real state and print the block to paste
---
No arguments. Target `docs/status/access.md` — one file for both tracks, every row tagged `[api]`
or `[web]`. Unlike `.claude/state/`, this file is tracked, so it is a reviewable record and a
stale line in it is a bug, not a note to self.

1. `git fetch --all --prune`. Take the real anchor: `git rev-parse --short origin/main`, and the
   highest number from `gh pr list --state all --limit 5 --json number`.
2. Rewrite the file keeping its section order exactly:
   - `Anchor: main @ <sha> · latest PR seen #<n>` — from step 1, never carried over.
   - `## Open PR (one at a time)` — from `gh pr list` + `gh pr checks`. More than one → say which
     is queued and that the queue rule was breached.
   - `## Merged since last update` — PRs merged since the old anchor, one line each, tagged.
   - `## In progress` — branch, whether it is pushed, what is left.
   - `## Next, in order` — one queue, each row tagged `[api]` or `[web]`. Drop merged rows and do
     not renumber the rest. The order is: an item that unblocks the other track first, otherwise
     alternate `[api]` and `[web]`, and never two open PRs at once. One track running out means
     the rest of the queue is the other track — that is not a breach of the alternation.
   - `## Blocked / needs a decision` — What / Who. Drop anything now ruled and say where the
     ruling is recorded.
   - `## Decided without review — <owner> may object` — the ADR-015 log. What was decided, why,
     which file carries it. Never silently dropped; an entry leaves only when the owner answers.
   - `## Unmerged local branches` — branches with commits on no remote, what each contains, and
     what moves them. These are invisible to `gh` and get lost otherwise.
   - `## Contract changes between the tracks` — what one track owes the other. Both directions
     live here now; there is no second file to cross-reference.
   - `## Gate` — only commands that actually ran, with their real results.
   - `## Design: remaining differences from the reference` — `[web]` only, kept in this file.
3. Uncommitted work → commit it on the task branch as `wip(<scope>): <what>` with a `Refs:`
   footer. Never commit to `main`.
4. Print the changed sections as **one** ready-to-paste block. Report by task ID and state — no
   dates, no checkpoint names, no Yesterday / Today framing.
