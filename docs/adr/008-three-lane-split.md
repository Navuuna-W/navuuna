# ADR-008 — Three-lane build split: Signal, Core, Access

- **Number:** 008
- **Status:** Accepted
- **Decider:** Devyan (Signal — Founder, CTIPSO; product owner, DEC-01)
- **Refs:** work packs rev 3 (27 Sep); ADR-011 (checkpoints + audience); ADR-004a
  (one writer per table, DB role grants, Redis stream contracts); Bible §4.1 (team),
  §4.2 (components), §12 (ownership), §19 (change log)

## Context

Bible v1.1 names three builders — Devyan (Signal), Khillon (Core/CTO Backend) and
Austine (Access/CTO Frontend) — but it does not say which component each builder owns,
who reviews what, or where a piece of work goes when the Bible's assignment doesn't
match where the code naturally lives. The work packs rev 3 (sent 27 Sep) proposed a
three-lane split with every component, every table and every PR reviewer named
explicitly. This ADR writes that split down so it is not re-litigated.

Two facts from the surrounding ADRs are load-bearing:

- **ADR-004a** amended Bible §10.1: Python owns `raw.*`, `records.*`, `core.entities`,
  `core.sources` and `scores.sub_variable_scores`; Laravel owns `scores.variable_scores`,
  `flags.*`, `audit.events`, `lens.*`, `core.observations`, `core.consents` and the
  framework tables in `public`. Writers are enforced by DB roles (`nv_ingest`,
  `nv_signals`, `nv_app`), not by convention. One table has exactly one writer.
- **ADR-011** confirmed the three-lane split's assumption that the Signal lane (Devyan)
  owns the 7 Oct product scope and the trim decisions (DEC-21/22/23). That makes Devyan
  the editorial owner of what gets built, independent of who writes the code.

The Bible left two concrete misalignments the work packs fix:

1. The Bible lists FR-07/FR-08 water + roads adapters as "Devyan spec, Khillon code".
   The code is in `services/signals/modules/`, where Devyan is the natural author (he
   knows the domain). Keeping the spec/code separation adds a reviewer round-trip
   without changing the code. Fold both into Signal; Core reviews.
2. The Bible lists the JSON API (FR-16), MVT tiles, lens application (FR-12), Reverb
   broadcasts and observations under Core. Those endpoints are what the frontend
   consumes and the shape of the contract is a frontend concern. Moving them into
   Access lets the lane that depends on a contract own its shape; Core still owns the
   engine, migrations, auth and the signal-service runner.

## Decision

**Three lanes, one owner per lane. Lane owners merge their own PRs after review.**

### Signal lane — Devyan (Founder, CTIPSO)

Everything that turns sources into scores plus every product decision.

- Scrapers, parser, aligners, review CLI (`services/ingest/**`).
- Base-layer loaders (`services/signals/engine/**` for the public-DB bits authored by
  Khillon; Devyan owns the data they load, FR-01, D-01/D-02).
- Earth observation (`services/signals/modules/**` openEO + CHIRPS).
- **All 17 adapters (FR-07 water, FR-08 roads, and the rest).** The Bible's
  spec/code split is withdrawn.
- `weights.yml`, lens JSONs (`lenses/*.json`), narratives, labels, thresholds.
- `docs/CONTEXT.md` (vocabulary).
- Nightly provenance walk (NFR-01).
- Prefect ingest flows.
- Product decisions: DEC-21/22/23, DEC-08/09/10/11/14/15/17/20, A6/A7 and the open
  questions tracked in `apps/web/README.md`.

### Core lane — Khillon (CTO Backend)

The platform the other two lanes build on.

- Laravel 13 scaffold, PHP + Composer dependencies, Pint + PHPStan L6 + Pest config
  (`apps/api/**`, except the sub-paths named below under Access).
- **Every database migration** (`apps/api/database/migrations/**`), including the tables
  Python writes. One migration file per table per ADR-004a.
- Models with `protected $table = '<schema>.<table>'`.
- Rollup engine + 3-state gates (Laravel; `app/Engine/`).
- Findings engine + FlagWorkflow + review endpoints + audit (`app/Findings/`, routes
  `GET /flags`, `GET /flags/{id}`, `POST /flags/{id}/transition`).
- Auth: Sanctum SPA cookie, roles, API keys, rate limits (`app/Auth/`, `app/Http/
  Middleware/`, API-key issuing).
- Signal-service interface, registry and runner (Python at
  `services/signals/engine/**`); Prefect server + scoring flow.
- `signals.batch_written` reader; `signals.recompute_requested` writer (ADR-004a §3).
- Infrastructure: boxes, CI/CD, backups, monitoring, nginx (K-07), Reverb, Redis,
  release process, offline stack.
- `.github/**` governance (CODEOWNERS, labels, workflows except the `web` job).

### Access lane — Austine (CTO Frontend)

Everything the end user sees, plus the backend that serves it.

- **The frontend is Austine's alone** (`apps/web/**`): map (S1), entity panel (S2),
  finding detail (S3), review queue (S4/S5), observations modal (S6), sign-in (S7),
  states matrix (S8), Playwright E2E, screenshots, NFR-03 timing, video fallback.
- **`apps/web` dependencies and tooling** (ESLint 9 flat, Vitest, Playwright, Tailwind,
  pmtiles, MapLibre, openapi-fetch, Zustand, TanStack Query); the `web` job in
  `.github/workflows/ci.yml` (contributed to Core's workflow file by agreement).
- **Access-side `apps/api`** — the paths the frontend consumes:
  `app/Http/Controllers/Api/V1`, `app/Http/Resources`, `app/Http/Requests`,
  `routes/api.php`, `app/Tiles`, `app/Lens`, `app/Broadcasting` +
  `routes/channels.php`, `app/Observations`. OpenAPI generation (A-06).
- JSON endpoints `/api/v1/entities`, `/api/v1/entities/{id}`,
  `/api/v1/entities/{id}/scores`, `/api/v1/entities/{id}/flags`, `/api/v1/lenses`.
- MVT tile endpoint + tile cache.
- Lens application: `php artisan lens:sync` → `lens.lenses`, `LensApplier` on
  `EntityScored` → `lens.lens_scores`.
- Reverb broadcast layer (channels, events, auth) and the client-side Echo wiring.
- Observations backend (`POST /observations`, `POST /observations/{id}/withdraw`), EXIF
  strip, consent capture.
- `docs/contracts/*` (OpenAPI draft, tile contract).
- ADR-002 (frontend framework, accepted), this ADR, DEC-03/12/13.

### Review map

Every PR has one required reviewer. Author never approves their own PR. Review within
24 h.

| Path | Author | Required reviewer |
|---|---|---|
| `apps/web/**` | Austine | Devyan (PRD conformance) |
| `apps/api` Access-side paths above | Austine | Khillon |
| `apps/api` Core-side (Engine, Findings, Auth, Audit, Consumers) | Khillon | Devyan (§6 rules) |
| `apps/api/database/migrations/**` | anyone who authored the table | Khillon approves, Austine reviews |
| `services/signals/engine/**`, `services/flows/score_*` | Khillon | Devyan |
| `services/signals/modules/**` | Devyan | Khillon |
| `services/ingest/**`, `data/**`, `services/flows/ingest_*` | Devyan | Austine (Khillon for anything that writes to the DB) |
| `infra/**`, most of `.github/**` | Khillon | Austine |
| `docs/CONTEXT.md`, `docs/modules/**` | Devyan | Austine |
| `docs/contracts/**` | Austine | Khillon (he consumes it from the API side) |
| `docs/adr/**` | the decider | all three (per Bible §14.9 when the ADR crosses lanes) |

### Writer-per-table matrix

Unchanged from ADR-004a §Appendix. The three-lane split does not create new writers;
it only tells each lane which existing writer it owns.

| Writer role | Owned by | Writes |
|---|---|---|
| `nv_ingest` | Signal (Devyan) | `raw.*`, `records.*`, `core.entities`, `core.sources` |
| `nv_signals` | Core (Khillon — service runs the registry + runner) | `core.adapters`, `scores.sub_variable_scores` |
| `nv_app` (engine + findings paths) | Core (Khillon) | `scores.variable_scores`, `flags.*`, `audit.events`, framework tables in `public` |
| `nv_app` (API paths) | Access (Austine) | `core.observations`, `core.consents`, `lens.lenses`, `lens.lens_scores` |

## Consequences

- **Bible §4.2 and §12 are superseded** by this split. Where they conflict, this ADR
  wins (per Bible §14.9).
- **Bible §19 (change log)** gets an entry at v1.2 pointing at this ADR, together with
  ADR-004a and ADR-011. Devyan does that edit when Bible v1.2 ships.
- **CODEOWNERS was already updated** to match the Review map above (ADR-001 §Enforcing
  without branch protection). This ADR is the written source of truth; the file is the
  automation layer.
- **Khillon's CI workflow file** (`.github/workflows/ci.yml`) carries the `web` job that
  Access owns. The job runs typecheck, lint, test and build inside `apps/web`; its
  scope is scoped by `working-directory`. If Core later splits CI into separate files,
  the `web` job moves to a file Access owns.
- **No new lanes.** If a new domain lands (energy, safety, land past buildings), it is
  one of the three existing lanes picking it up — not a fourth owner.
- **Lane boundaries bind PRs.** A PR that touches two lanes needs two reviewers (one
  from each lane); a PR that touches three needs all three. The in-lane reviewer
  signs off their lane's slice.
- **Devyan is a notifier for open questions, not a gate.** Where a question has a
  defensible default, the owning lane ships that default and files the question
  (currently kept in `apps/web/README.md` for the Access lane). Devyan's answer, when
  it lands, is a follow-up PR.

## Why not keep the Bible's component ownership

Three reasons:

1. **Where the code lives matters.** Moving FR-07/FR-08 adapter code into the Core lane
   produced a review loop between the domain owner (Devyan) and someone who did not
   write the adapters (Khillon). Putting both spec and code with the domain owner
   removes the loop. The engine still enforces the §6 rules; Khillon reviews.
2. **The API's shape is a frontend concern.** The frontend is the only consumer of the
   JSON API, MVT tiles, lens output and Reverb events in v1. If Core owned the
   contract, every endpoint shape change would need two sign-offs. Putting the Access
   consumer in the author seat removes the loop; Core still owns the engine behind the
   contract, authentication, and the migrations the API runs on.
3. **One writer per table is already enforced in the DB.** ADR-004a's `nv_app` /
   `nv_signals` / `nv_ingest` grants make it impossible for the wrong service to write
   a table. Splitting the API work across lanes doesn't create a new writer because
   the Access-lane endpoints still write as `nv_app` to tables owned by that role.

## Alternatives considered

- **Stay with Bible §4.2 as written.** Rejected on the two reasons above.
- **Fold Access back into Core (Khillon owns `apps/api` entirely, Austine owns only
  `apps/web`).** Rejected because the Access-side endpoints (`/api/v1/entities`,
  tiles, lens application) are the ones that must satisfy NFR-02 and PRD §10 — rules
  the frontend lane polices every day. Putting them in a reviewer-only position
  weakens the enforcement.
- **A fourth lane for QA (Playwright, screenshots, release checks).** Rejected because
  the frontend owner is the only person who can tell when the UI is wrong without
  reading the diff. QA is Access-lane work.
