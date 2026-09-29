# ADR-001 — One monorepo, and how we protect `main` on the GitHub Free plan

- **Number:** 001
- **Date:** 29 Sep 2026
- **Status:** Accepted
- **Decider:** Khillon (Core — owns `infra/` and `.github/`, Bible §12)

## Context

Navuuna has a Laravel API, a web app, Python ingest and signal services, Prefect flows and
shared docs. They change together: a new migration, the adapter that writes into it and the
panel that shows it usually land in the same week. Three people build all of it in eight
working days before 7 Oct 2026.

The Bible (§14.1, §14.2) asks for one repository and a protected `main` (one approving
review, CI green, no direct pushes, squash-merge only). Two facts differ from the Bible:

1. The GitHub organisation is `Navuuna-W`, not `navuuna`. The repository is
   `github.com/Navuuna-W/navuuna`.
2. The repository is private on the GitHub Free plan. On that plan GitHub does not enforce
   branch protection rules or required CODEOWNERS reviews on private repositories.

## Decision

**One monorepo** at `github.com/Navuuna-W/navuuna`, laid out as Bible §14.1.

**Protecting `main` without branch protection.** We enforce the same rules in three places:

| Rule (Bible §14.2) | Where it is enforced |
|---|---|
| Squash-merge only | Repository settings: merge commits and rebase merges are switched off |
| No direct push to `main` | Local `pre-push` hook (`.githooks/pre-push`), plus a `no-commit-to-branch` hook |
| Conventional Commits with `Refs:` | Local `commit-msg` hook and the `commitlint` CI job, which also checks the PR title (the PR title becomes the squash commit on `main`) |
| CI green before merge | CI runs on every PR; the reviewer does not merge a red PR |
| One approving review, by the area owner | `.github/CODEOWNERS` names the reviewer; the author never merges their own PR |

Every developer runs the hook setup once per clone (see `.github/README.md`).

## Consequences

- A developer who skips the hook setup can still push to `main`. The remaining guard is
  people: the team rule in `CLAUDE.md` §6 and review of every PR. We accept this risk
  for the 7 Oct build.
- CODEOWNERS is advisory on this plan. Reviewers may need to be requested by hand.
- If the organisation moves to GitHub Team, we switch on real branch protection with the
  same rules and keep the hooks as a fast local check. That change does not need a new ADR.
- All badges, links and CODEOWNERS entries use `Navuuna-W/navuuna`.
