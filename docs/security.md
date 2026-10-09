# Security checklist

The security pass for the 7 Oct release (work pack K-19, NFR-05). Each line names the thing
that proves it: a test, a CI job, a config file or a check run by hand. The scope follows the
7 Oct trim (DEC-23). The full OWASP top 10 walk-through comes after 7 Oct.

**How to use this file:** when something here changes, update its line in the same PR. The
checklist is signed once every line is ✅. Lines marked ⏳ wait on the servers (K-04 / K-07).

Status: ✅ done and checked · ⏳ waiting on a named task · ⚠️ known gap, owner named

## Checklist

| # | Rule | Status | Evidence |
|---|---|---|---|
| 1 | HTTPS only: HTTP redirects to HTTPS on staging and production | ⏳ K-07 | nginx config (K-07); check with `curl -I http://<host>` → 301 |
| 2 | HSTS header on every HTTPS response | ⏳ K-07 | nginx `add_header Strict-Transport-Security "max-age=31536000" always;`; check with `curl -I https://<host>` |
| 3 | Session cookie only over HTTPS, not readable by JavaScript | ⏳ K-07 | `SESSION_SECURE_COOKIE=true` in the server `.env`; `http_only` is on by default (`config/session.php`) |
| 4 | Secrets only in `.env`, never in git | ✅ | `.env` and `.env.*` are in `.gitignore`; CI job `secret-scan` (gitleaks) on every PR's commits; full-history scan below |
| 5 | Postgres and Redis never reachable from the internet | ⏳ K-04 | Box firewall allows only the private network; check from a laptop with `nc -zv <public ip> 5432` and `6379` → refused |
| 6 | Local stack does not expose the database to the network | ✅ | `infra/docker-compose.yml` binds every port to `127.0.0.1` |
| 7 | Same origin: no CORS | ✅ | `apps/api/config/cors.php` (`paths => []`); `tests/Feature/Security/CorsTest.php` |
| 8 | CSRF check on every session request | ✅ | `statefulApi()` in `apps/api/bootstrap/app.php`; `tests/Feature/Security/CsrfTest.php` (no token / wrong token → 419) |
| 9 | Mass-assignment guards | ✅ | Eloquent models are guarded by default; only `User` and `ApiKey` declare `#[Fillable]`, and both are created only by console commands from validated input (`users:create`, `keys:issue`) |
| 10 | API keys stored hashed, shown once | ✅ | SHA-256 in `public.api_keys` (K-11b); `tests/Feature/Auth/IssueApiKeyCommandTest.php` |
| 11 | 60 requests/min per key, 429 body | ✅ | `throttle:api` limiter (K-11b); `tests/Feature/Auth/RateLimitTest.php` |
| 12 | PHP dependencies have no known advisories | ✅ | CI job `dependency-audit` → `composer audit` (fails the build) |
| 13 | Python dependencies have no known advisories | ✅ | CI job `dependency-audit` → `pip-audit` (fails the build); pytest raised to ≥ 9.0.3 for PYSEC-2026-1845 |
| 14 | Web runtime dependencies have no high or critical advisories | ⚠️ Austine, #90 | `maplibre-gl` critical XSS, `react-router` moderate. CI job `dependency-audit` → `npm audit --omit=dev --audit-level=high` only reports until #90 is merged; then it fails the build |
| 15 | Upload validation (type, size) | ⏳ after 7 Oct | With Austine, when the upload route exists |

### Known and accepted until after 7 Oct

- **Web dev-only advisories:** `vite`, `vitest`, `tinypool`, `esbuild`. These run on laptops only and never ship. They're listed in #90 and upgraded with it.
- **Full OWASP top 10 walk-through:** moved after 7 Oct (DEC-23).

## Checks run by hand

| Date | Check | Result |
|---|---|---|
| 9 Oct 2026 | `gitleaks git` over the whole history of `main` (79 commits, up to 708c23e) | No leaks found |
| 9 Oct 2026 | `composer audit` in `apps/api` | No advisories |
| 9 Oct 2026 | `pip-audit` on the signal service, dev tools included | pytest 8.4.2 flagged → raised to ≥ 9.0.3; then no advisories |
| 9 Oct 2026 | `npm audit` in `apps/web` | 9 packages flagged (3 runtime) → #90 |

## Sign-off

Signed when every line above is ✅ or has been moved after 7 Oct by agreement.

| Name | Role | Date |
|---|---|---|
| Khillon Makwana | Core | |
| Devyan | Signal | |
