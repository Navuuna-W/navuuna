---
description: Start a work-pack task, e.g. /task K-09a
argument-hint: <task ID>
---
Task: $ARGUMENTS

1. Find the row: `grep -n "<base ID, e.g. K-09>" docs/workpacks/khillon.md` and read only that row plus the lines around it.
2. Read only the Bible sections that row refs (use the index in CLAUDE.md §2), and any ADR it names.
3. Check its "Needs". For each need that isn't in the repo yet, use the stub from the progress file's "Waiting on" table (or propose one and add it there).
4. Present a plan and STOP for approval:
   - files to create/change (only in my lane's paths — CLAUDE.md §3)
   - tests to write
   - the Done check command(s)
   - estimated changed lines; if > 400, propose a split (e.g. $ARGUMENTS-a / -b)
   - a 3–5 line plain-English explanation of how the pieces fit, as you'd tell a new developer
5. After approval: create branch `type/scope-short-description`, implement, follow CLAUDE.md §5 (simple code), run the Done check and the lint/type/test commands, do the §5 new-developer self-review of the full diff and fix anything unclear, then commit with footer `Refs: $ARGUMENTS, <FR/ADR>`.
6. Update the "Now" block of the progress file after each meaningful step (one line), so a crash loses nothing.
