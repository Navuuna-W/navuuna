# ADR-013 — What happens after a finding is contested

- **Number:** 013
- **Date:** 7 Oct 2026
- **Status:** Proposed (accepted on merge)
- **Decider:** Khillon (Core — owns FlagWorkflow, K-14)
- **Refs:** Bible §6.5, ADR-010 (DEC-10, E8), K-14

## Context

Bible §6.5 gives the lifecycle `detected → held → explanation_checked → published | dismissed`,
plus `contested → resolved`. A contest is the named party saying the finding is wrong, so
it has two outcomes: we agree, or we don't. The Bible lists only the first. Without a way out
for the second, a rejected contest would stay `contested`, and hidden from viewers, forever.

## Decision

1. **`published → contested`**: an analyst or admin records the contest with a note.
2. **`contested → resolved`**: we agree with the contest. An analyst or admin, with a note.
3. **`contested → published`** (new): we reject the contest and the finding stands. An
   analyst or admin, with a note saying why.

`contested` is not a public state, so while a contest is open the finding is hidden from
viewers, the same as `held`. Each move goes through FlagWorkflow and writes
`flags.transitions` and `audit.events` like every other transition.

The review endpoint names these moves with actions, not target states (ADR-010 DEC-10):
`contest`, `resolve` and `reject_contest`. (Not "uphold": it is unclear whether the
contest or the finding is being upheld.)

## Consequences

- FlagWorkflow's table of legal moves has one more human row than the Bible's diagram.
- Austine's review screen needs Resolve and Reject contest buttons on a contested finding.
