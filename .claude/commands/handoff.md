---
description: A teammate delivered something — swap the stub for the real thing
argument-hint: <item, e.g. "weights.yml" or "500-entity fixture">
---
Delivered: $ARGUMENTS

1. Find the row in "Waiting on" in `.claude/state/progress-khillon.md`.
2. `git fetch && git log --oneline origin/main -10 -- <swap-trigger path>` to confirm it's on main.
3. On a new branch, replace the stub with the real input. Keep the stub only if tests still need a small fixture.
4. Run the Done checks of every task that used this stub. Report what broke, if anything, and fix it in my lane only.
   If the real input contradicts the stub in a way that needs their change, write the exact question for the teammate instead of editing their files.
5. Mark the row swapped, update affected task statuses, then `/checkpoint`.
