---
description: Push, open the PR, wait for CI, squash-merge when green
---
No arguments. The track comes from the diff — `apps/api/**` → api, `apps/web/**` → web, both →
both — and only decides which gate commands `/gate` ran. Never ask which.

Merges without an approval under `docs/adr/015-access-lane-self-merge.md`. That ADR covers the
**Access lane only** — root `CLAUDE.md` §6.0 still binds Core and Signal, so never run this on
their work.

1. **Refuse, and say which rule, if any of these hold:**
   - the branch is `main`
   - `/gate` steps 1–4 do not pass on this exact HEAD (re-run them if the last run was on an
     older commit)
   - the last `/audit` had anything in its **blocking** list
   - a changed path is outside the ADR-008 Access paths and is not covered by a standing ruling
     in `docs/status/COMMANDS.md`
   On a refusal, report what failed and stop. Do not push.
2. `git push -u origin HEAD`. Never force-push `main` or any shared branch. `--force-with-lease`
   only, only on this branch, and only after a rebase.
3. `gh pr create` with the title and body `/gate` produced. Request the CODEOWNERS reviewer with
   `--reviewer` as FYI — ADR-015 does not wait on them.
4. `gh pr checks --watch`. On failure: show the failing job's log, fix on this branch, push, watch
   again. **Stop and report after two failed fix attempts** — a third is a sign the diagnosis is
   wrong, not the fix.
5. Green → `gh pr merge --squash --delete-branch`. Then `git fetch origin`,
   `git switch --detach origin/main`, and delete the local branch. Never check out `main` inside a
   worktree.
6. Reply with: PR number · merge sha · the next item from `docs/status/access.md` with its
   `[api]` / `[web]` tag. Then run `/wrap` so the status file reflects the merge.
