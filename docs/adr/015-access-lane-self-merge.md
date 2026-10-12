# ADR-015 — Access lane self-merge

- **Number:** 015
- **Status:** Accepted — Khillon and Devyan may object; an objection becomes a follow-up PR.
- **Decider:** Austine (Access — CTO Frontend)
- **Refs:** ADR-008 (three-lane split, review map); root `CLAUDE.md` §6.0; `apps/web/CLAUDE.md`;
  Bible §14.4 (PRs), §14.8 (definition of done), §14.9 (an ADR beats the Bible)

## Context

Root `CLAUDE.md` §6.0 says "Never merge a PR — a human teammate reviews, approves and merges",
and ADR-008's review map says "Author never approves their own PR." Both assume a reviewer
answers within 24 h.

In practice the Access lane has been the one waiting. At the time of writing, five Access PRs
sat green and mergeable for days, and three lanes' worth of work queued behind the two-open-PR
cap. The Access lane also carries the frontend alone, so there is no second pair of Access eyes
to recruit; waiting converts a one-person lane into a zero-person lane.

The review was doing two jobs: catching rule breaches, and catching mistakes. The first is
mechanisable — the rules live in `CLAUDE.md` §4/§5, `apps/web/CLAUDE.md`, the merged ADRs and
the Bible, and CI already enforces commit format, lint, types, tests, migration hygiene and
secret scanning. The second needs a human, but it does not need that human *before* the merge:
on a squash-merge repo an objection is a follow-up PR, not a rollback.

## Decision

**Access-lane PRs merge on green CI without an approval.**

A merge requires all four:

1. `/gate` passes — under 400 changed lines, every path inside the Access lane, commitlint clean
   on every commit and on the PR title, and the track's gate commands green.
2. `/audit` reports **nothing blocking**. This is what stands in for the human review: it holds
   the diff against the merged ADRs, `docs/BUILD_BIBLE.md`, `docs/CONTEXT.md` and
   `apps/web/CLAUDE.md`, and it flags overclaims.
3. CI green on the PR head — all checks, not a subset.
4. The merge happens through `/ship`, never by hand.

**Teammates review after the merge.** The required reviewer from `.github/CODEOWNERS` is still
requested on every PR, as FYI. Any objection becomes a follow-up PR; nothing is reverted by
default.

### Scope — Access lane only

This ADR changes nothing for the Core or Signal lanes. Root `CLAUDE.md` §6.0 stays as written
and remains their default.

It applies only to the Access-lane paths as ADR-008 defines them: all of `apps/web/**`, and in
`apps/api` the named paths `app/Http/Controllers/Api/V1`, `app/Http/Resources`,
`app/Http/Requests`, `routes/api.php`, `app/Tiles`, `app/Lens`, `app/Broadcasting`,
`routes/channels.php`, `app/Observations`. `app/Http/Controllers/Auth`,
`app/Http/Controllers/Findings` and `app/Http/Middleware` are Core's and are **not** covered.

Outside those paths, the only Access changes permitted are the small FYI PRs already ruled: the
`composer.json` `dedoc/scramble` line, thin wrappers in `app/Console/Commands`, migration M2's
`colour_class` CHECK, and the contract calls on S2 and DEC-20 display names. Each is a separate
small PR naming the owner in its body, and each changes only what it must — never another
lane's logic.

**`main` is never pushed to directly, and a shared branch is never force-pushed.** `/ship` uses
`--force-with-lease`, and only on its own branch after a rebase.

### What this overrides, for the Access lane only

- Root `CLAUDE.md` §6.0's "Never merge a PR — a human teammate reviews, approves and merges."
  The rest of §6.0 stands, including "never push to `main`" and "never merge with red CI".
- ADR-008's "Author never approves their own PR." Self-merge here means merging **without** an
  approval. No one approves their own PR; the approval step is removed, not self-served.

Per Bible §14.9 and `CLAUDE.md` §1 (merged ADRs, newest wins), this ADR is the operative rule
where it conflicts with either.

## Consequences

- **`/audit` becomes load-bearing.** It is the review. A gap in `/audit` is a gap in the
  process, so a rule it misses gets added to it rather than remembered.
- **A bad merge is cheap but not free.** Squash-merge keeps `main` linear, so a bad change is one
  revert PR. The cost lands on whoever notices, which is why the CODEOWNERS reviewer is still
  requested.
- **Decisions that belong to another lane still get made.** Where Access has a defensible
  recommendation it applies it, logs it in `docs/status/*.md` under "Decided without review —
  <owner> may object", and keeps going. It stops only when it has no recommendation. This is
  ADR-008's "Devyan is a notifier for open questions, not a gate", applied to all three owners.
- **ADR-008's review map is now partly advisory.** Its Access rows name a required reviewer;
  under this ADR that reviewer is requested but not awaited. The Core and Signal rows are
  unchanged.
- **`.github/CODEOWNERS` is unchanged.** On the Free plan it was always advisory (ADR-001).
  Requesting the reviewer is still how a teammate learns a PR exists.
- **This ADR's own path is shared.** `docs/adr/**` routes to all three owners in CODEOWNERS, so
  the PR introducing this file cannot itself satisfy "every path inside the Access lane". It is
  merged under the FYI-PR exception above, with all three owners requested.

## Alternatives considered

- **Keep review-first and chase reviews harder.** Rejected: the queue is the symptom, not the
  cause. Two of three owners are on other lanes' critical paths, and a one-person lane cannot
  escalate to itself.
- **Self-merge with no `/audit` requirement.** Rejected. The review was catching real rule
  breaches — held-finding visibility and score-without-coverage among them. Dropping the gate
  entirely trades a slow lane for an unsafe one.
- **Extend self-merge to all three lanes.** Rejected: not Austine's decision to take. Core and
  Signal keep §6.0 until their owners say otherwise.
- **Branch protection requiring one approval.** Rejected: not available on the Free plan
  (ADR-001), which is the premise of §6.0 in the first place.
