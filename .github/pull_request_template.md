<!-- PR title = the squash commit on main: `type(scope): imperative summary` (Bible §14.3). Keep the PR ≤ 400 changed lines. -->

## What
One paragraph. Which FR / sub-variable / module.

## Why
The requirement or decision this serves. Link the ADR if one exists.

## Where
Files and directories touched. Which layer (L1/L2/L3/L4, or app).

## How to test
Exact commands or clicks. Include a staging entity ID if relevant.

## Checklist
- [ ] Tests added or updated
- [ ] No sub-variable added or removed (Layer 2 frozen)
- [ ] No sub-variable weights in a lens config (Layer 4 weights variables only)
- [ ] Scores emit value, score, confidence, status, observed_at, source_ids
- [ ] Missing data is null_not_measured with a reason — never 0, never a default
- [ ] Only the owning service writes to the touched tables (ADR-004)
- [ ] Migration is reversible (down() is implemented)
- [ ] docs/modules or ADR updated if behaviour changed
- [ ] No secrets, no data files
- [ ] A new developer could follow this without asking the author

Refs: <task ID>, <FR/ADR>
Closes #<issue>
