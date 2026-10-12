---
description: Hold the current branch against every PR rule before it is shipped
---
No arguments. Run all checks, report every failure at once, then end with the verdict. This command
never pushes and never opens a PR — that is `/ship`, under ADR-015.

**Track comes from the diff, never from a question.** `git diff --name-only origin/main...HEAD`:
any `apps/api/**` → run the api gate · any `apps/web/**` → run the web gate · both → run both ·
neither (docs, `.claude`, CI config only) → say which commands were skipped and why.

1. **Size.** `git diff --stat origin/main...HEAD`. Over 400 changed lines → propose a split by
   task-ID part (e.g. A-07a / A-07b) and stop there; the rest can wait for the split.
2. **Lane boundary.** Check each changed path against `docs/adr/008-three-lane-split.md` — **not**
   root `CLAUDE.md` §3, whose bare "`apps/api` Http" over-grants. Access owns all of `apps/web`,
   plus `app/Http/Controllers/Api/V1`, `app/Http/Resources`, `app/Http/Requests`, `routes/api.php`,
   `app/Tiles`, `app/Lens`, `app/Broadcasting`, `routes/channels.php`, `app/Observations`.
   `app/Http/Controllers/Auth`, `app/Http/Controllers/Findings` and `app/Http/Middleware` are
   **Core's**. ADR-015 adds three docs paths Access writes: `docs/status/access.md`,
   `docs/contracts/**` and `docs/design/**`. `docs/CONTEXT.md` is Devyan's and is not among them.
   Name every out-of-lane file, which lane owns it, and whether a standing ruling in
   `docs/status/COMMANDS.md` covers it. An out-of-lane file with no ruling is a failure.
3. **Commits.** `npx commitlint --from origin/main --to HEAD --verbose`. Then state the proposed
   PR title and confirm it passes the same rules — type and scope from `commitlint.config.mjs`,
   ≤ 72 chars, imperative, with a `Refs: <task ID>, <FR/ADR>` footer on every commit.
4. **Gate commands** for the track(s) the diff selected. web → `npm run typecheck`, `lint`, `test`,
   `build`. api → `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pest`.
   Paste the real output, not a summary.
5. **PR body.** Fill `.github/pull_request_template.md`: What / Why / Where / How to test, every
   checklist line ticked or explained, `Refs: <task ID>, <FR/ADR>`, `Closes #<issue>`. No issue
   exists → say so rather than write a number. A `[web]` change → attach the screenshots it needs.
6. **Reviewer — FYI only.** Name the `.github/CODEOWNERS` reviewer so `/ship` can request them.
   Under ADR-015 nothing waits on them. A path CODEOWNERS does not cover → say it is uncovered and
   name the ADR-008 owner instead of guessing.
7. End with one of exactly two verdicts:
   - **`ready to /ship`** — every check above passed.
   - **the numbered list of failures** — what failed, the real output, and the smallest fix.
   Never ask whether to push.
