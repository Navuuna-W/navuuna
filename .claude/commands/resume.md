---
description: Pick up exactly where the last session stopped
---
1. Read `.claude/state/progress-khillon.md`. Do not read the Bible or work packs yet.
2. Run `git status`, `git branch --show-current` and `git log --oneline -10`.
3. For every "Waiting on" row, check whether its swap-trigger path now exists (`ls` / `git log --oneline origin/main -20 -- <path>` after `git fetch`). Do not open the files.
4. Do NOT explore the codebase or re-verify tasks marked done.
5. Reply in at most 8 lines:
   - current task, last step done, next step
   - any Waiting-on row now swappable → suggest `/handoff <item>`
   - any Owed row due today or overdue (today is the system date)
   - anything blocked
Then stop and wait for my go.
