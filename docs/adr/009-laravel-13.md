# ADR-009 — Laravel 13 on PHP 8.3, not Laravel 11

- **Number:** 009
- **Date:** 29 Sep 2026
- **Status:** Accepted
- **Decider:** Khillon (Core — owns DEC-04 and the `apps/api` Engine)

## Context

Bible v1.1 §8.1 locks the backend to **Laravel 11 / PHP 8.3**. That row was written before
we checked the Laravel support calendar:

- Laravel 11 security fixes ended on **12 Mar 2026**. A codebase started today on 11 would
  be unpatched from its first commit, and we would owe an upgrade straight after 7 Oct.
- Laravel 13 is the current release and runs on PHP 8.3, so the PHP pin in §8.1 still holds.
- Sanctum (auth, K-11), Reverb (real-time) and queues are first-party packages in 13, so
  nothing the Bible relies on is lost.
- No Laravel code exists in the repo yet (`apps/api` is not created). Changing the version
  now costs nothing; changing it after `laravel new` means a framework upgrade mid-build.

Options considered:

| Option | Why not / why |
|---|---|
| Stay on Laravel 11 | Out of security support. Rejected. |
| Laravel 12 | Supported, but its security window ends much sooner than 13's, so we would upgrade again soon after launch. Rejected. |
| **Laravel 13** | Current release, longest support window, same PHP 8.3 pin. **Chosen.** |

## Decision

1. The backend is **Laravel 13 on PHP 8.3**. This replaces the Backend row of Bible §8.1
   and nothing else in that table.
2. `apps/api/composer.json` pins `"php": "^8.3"` and `"laravel/framework": "^13.0"`.
3. Sanctum, Reverb and queues use their first-party Laravel 13 packages. No third-party
   replacements.
4. `laravel new` (K-06) runs only after this ADR is merged.

## Consequences

- CLAUDE.md §1 and the work packs (DEC-04) already name this override; the Bible text is not
  edited, and this ADR wins over it (ADRs rank above the Bible).
- The `php` CI job (K-05) and the VitoDeploy PHP version on Box A (K-04) use PHP 8.3.
- Any Composer package we add must support Laravel 13. If one does not, we pick another
  package; we do not drop the framework version.
- Moving to a later major version (Laravel 14 or above) needs a new ADR.
