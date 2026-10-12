---
description: Review the current branch against the rules and flag anything overclaimed
---
No arguments. The task ID comes from the `Refs:` footers on this branch's commits
(`git log origin/main..HEAD --format=%B`); none found → say so and review against the rules
anyway. The track comes from the diff: `apps/api/**` → api, `apps/web/**` → web, both → both.
Never ask which.

Read the diff as the reviewer, not the author. Under ADR-015 this **is** the review that stands
in for a human approval, so a rule missed here ships. Report findings; change nothing except
where step 5 says to.

1. `git diff origin/main...HEAD`, then read every changed file in full — not just the hunks.
2. Check against the sources, in `CLAUDE.md` §1 order: merged ADRs newest first, then the work
   pack row, then `docs/BUILD_BIBLE.md` §§ it cites (use the §2 index; read only those ranges),
   then `docs/CONTEXT.md` for vocabulary and `apps/web/CLAUDE.md` for the frontend rules.
   **`docs/PRD.md` is deliberately out of the repo.** A claim that would rest on it is reported
   as "unverifiable — ask Austine". Never blocking, never guessed.
3. Check the hard rules: `CLAUDE.md` §4 (unmeasured is `null_not_measured` with a reason, never
   0; no score without coverage and confidence; no held finding in front of a non-analyst in any
   form; one writer per table; schema-qualified models; real `down()`), `CLAUDE.md` §5 (a new
   developer follows it in one read), and for `apps/web` the nine numbered product rules in
   `apps/web/CLAUDE.md`.
4. **Overclaim check.** For every "done" in the branch, `docs/status/access.md`, the commit bodies
   and the PR body, name the evidence. Separate these explicitly:
   - built against `tests/fixtures/` or `apps/web/fixtures/` — **not** proven against the API
   - passing locally — **not** green in CI
   - merged — **not** deployed to staging
   The bar is `apps/web/CLAUDE.md` "Done means": merged, CI green, on staging, shown at the next
   checkpoint, tests added, docs/ADR updated. Anything short of it is reported as what it is.
5. **Decisions that belong to someone else.** For each one — another lane's file, a weight
   change, a new dependency, a vocabulary word (`docs/CONTEXT.md` is Devyan's), or two sources in
   conflict:
   - **With a defensible recommendation:** apply it, add a line to `docs/status/access.md` under
     `## Decided without review — <owner> may object` saying what was decided, why, and which
     file carries it, then keep going. Do not stop.
   - **With no recommendation:** stop and ask. Name the person and the exact one-line question.
6. Reply as: **blocking** · should fix · overclaims quoted with corrected wording · decided
   without review, with the owner named · open questions for a human.
   **The blocking list must be empty before `/ship`** (ADR-015). Anything blocking → fix it and
   re-run this command.
