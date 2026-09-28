---
description: Save state before stopping or clearing context
---
1. Rewrite `.claude/state/progress-khillon.md`:
   - "Now": current task, branch, last step done, exact next step (specific enough to start cold).
   - Task table: status and PR number for anything that changed.
   - Waiting on / stub register: add new stubs, mark swapped ones.
   - Owed: mark delivered items.
   - Session log: one line, drop entries beyond the last 5.
   - Keep the file under ~150 lines.
2. If there is uncommitted work, commit it on the task branch as `wip(<scope>): <what>` (squash-merge removes it later). Never commit to main.
3. Print a ready-to-paste 10:00 async message: Yesterday / Today / Blocked (name the person and the item).
