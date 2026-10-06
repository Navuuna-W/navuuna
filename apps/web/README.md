# apps/web — Navuuna frontend

React 19 + TypeScript strict + Vite 5 + Tailwind v4. See `docs/adr/002-frontend-framework.md`
for the stack and why.

## Status

Every row below is honest about what is actually true today. "Done" means Bible §14.8 —
merged to `main`, CI green, deployed to staging, against the real Laravel API. Everything
that only works against the fixture mock is marked **built (fixtures)** — not done, not
mergeable until the real API endpoint ships, and the UI is one env flag away from pointing
at it (`VITE_DATA_SOURCE=api`).

| Feature                                                    | State                | Needs for "done"                                                               |
| ---------------------------------------------------------- | -------------------- | ------------------------------------------------------------------------------ |
| Scaffold, ESLint 9 flat, Vitest, Playwright, `web` CI job  | **done (local)**     | PR merge + first green run on `main`                                           |
| ADR-002 React stack decision                               | **drafted**          | PR merge                                                                       |
| Self-hosted Protomaps basemap pipeline (`npm run basemap`) | **built (fixtures)** | K-07 nginx range-request config on Box A                                       |
| Tile contract `docs/contracts/tiles.md`                    | **drafted**          | Match the Laravel tile endpoint when A-09 ships                                |
| OpenAPI draft `docs/contracts/openapi.draft.yaml`          | **drafted**          | Replaced by Laravel-generated spec at A-06                                     |
| MVT + `/api/v1/entities/{id}` served by dev mock           | **built (fixtures)** | A-05/A-08/A-09 real endpoints behind K-11 auth                                 |
| Illustrative-data banner (gated on `VITE_DATA_SOURCE`)     | **built (fixtures)** | Hidden automatically when `VITE_DATA_SOURCE=api`                               |
| Entity panel S2 with coverage + confidence + accordion     | **built (fixtures)** | Live payload from the Laravel API                                              |
| `assertScored` NFR-02 guard                                | **built (fixtures)** | Serverside guard A-07 lands in parallel                                        |
| Finding detail S3                                          | **built (fixtures)** | Published findings from K-13 real findings engine                              |
| Viewer / Analyst role switch                               | **built (fixtures)** | Role comes from Sanctum session (K-11); switch is fixture-only by construction |
| County planner lens (weights, direction, bands)            | **built (fixtures)** | Loaded from Laravel `LensApplier` (A-11) using Devyan's `county_planner.json`  |
| Road segment line layer                                    | **built (fixtures)** | A real road + V5 scoring from Devyan                                           |
| Playwright smoke (preview + mock)                          | **built (fixtures)** | Second smoke against staging after A-06                                        |

### Blocked on

| Dep                                                 | Owner   | What                                                                       |
| --------------------------------------------------- | ------- | -------------------------------------------------------------------------- |
| K-01 CI base                                        | Khillon | Already merged.                                                            |
| K-06 Laravel scaffold + migrations                  | Khillon | So we can wire the real `/entities/{id}`                                   |
| K-07 nginx on Box A (range requests for `.pmtiles`) | Khillon | Staging deploy of the basemap                                              |
| K-11 Sanctum SPA auth + `/me`                       | Khillon | Real role comes from session; our client sends none in api mode            |
| A-06 Laravel OpenAPI generation                     | me      | Replaces `openapi.draft.yaml`                                              |
| ADR-008 three-lane split                            | me      | Not this session; cites ADR-011 + ADR-004a when written                    |
| `docs/CONTEXT.md` wording merge                     | Devyan  | Final sub-variable + variable labels (see "Pending label alignment" below) |
| `lenses/county_planner.json` merge                  | Devyan  | Reconciled by `fe82cb4` (fixture now matches direction axis)               |

### Pending label alignment (Devyan's `origin/docs/docs-context-glossary`, read-only compare)

The CONTEXT.md on Devyan's branch uses noun-phrase labels; our fixture uses the question
wording from CLAUDE.md. The labels in `src/copy/labels.ts` are **not changed yet** — this
is a pointer for the next session.

| Scope                                  | Where we are                                                           | Where Devyan's CONTEXT.md is                                                           |
| -------------------------------------- | ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| V1 label                               | "Is it working?"                                                       | "Activity" (question becomes a tooltip)                                                |
| V2 label                               | "Does the record match?"                                               | "Record vs reality"                                                                    |
| V3 label                               | "Which way is it going?"                                               | "Momentum"                                                                             |
| V4 label                               | "Will its inputs hold?"                                                | "Resource security"                                                                    |
| V5 label                               | "Can people reach it?"                                                 | "Access"                                                                               |
| Sub-variable labels                    | 11 ad-hoc rows in `fixtures/generate.ts` (e.g. "Does water come out?") | 28 frozen rows (e.g. 1.1 "Present", 1.2 "Working now"); gates/guards marked explicitly |
| Variable status word for `provisional` | "Partly verified" (badge)                                              | "Provisional"                                                                          |
| Panel name                             | "Entity details" (aria-label)                                          | "Passport" (DEC-20)                                                                    |
| Held finding label                     | shown with amber banner "Not visible to other users until published"   | adds "Under review (analysts only)" status word                                        |
| Finding "contested" state              | not modelled                                                           | `contested` state with "Someone has challenged this finding."                          |

When Devyan's branch merges, swap `src/copy/labels.ts` in one PR (one-file change by design).

## Install

```sh
cd apps/web
npm install
```

## Common commands

| Command              | What it does                                                         |
| -------------------- | -------------------------------------------------------------------- |
| `npm run dev`        | Start the Vite dev server at <http://localhost:5173>                 |
| `npm run build`      | Type-check, then produce a production bundle in `dist/`              |
| `npm run preview`    | Serve the built `dist/` locally on <http://localhost:4173>           |
| `npm run typecheck`  | TypeScript strict check, no emit                                     |
| `npm run lint`       | ESLint 9 (flat config) + Prettier `--check`                          |
| `npm run format`     | Prettier `--write`                                                   |
| `npm run test`       | Vitest run (one shot)                                                |
| `npm run test:watch` | Vitest in watch mode                                                 |
| `npm run basemap`    | Rebuild `public/basemap/nairobi.pmtiles` + glyphs + sprites (Step 3) |
| `npm run gen:api`    | Regenerate `src/api/schema.d.ts` from the OpenAPI draft (Step 4)     |

## Folder layout

```
src/
  api/          generated OpenAPI types + openapi-fetch client (no hand-written types)
  copy/         user-facing strings; a wording change is a one-file change
  findings/     finding detail S3 and related pieces
  map/          MapLibre view, style, pmtiles protocol registration
  panel/        entity panel S2
  realtime/     Laravel Echo client (added when A-21 lands)
  review/       review queue S4 and detail S5 (analyst/admin only)
  routes/       top-level route components composed into the router
  test/         Vitest setup
  ui/           shared, dumb UI components
```

## Basemap (self-hosted, offline)

The map tiles and labels ship with the app — no third-party host is contacted at runtime.

```sh
npm run basemap
```

This runs two scripts in `scripts/`:

- **`build-basemap.sh`** downloads the pinned `pmtiles` CLI into `tools/` (if missing), then
  probes `https://build.protomaps.com/YYYYMMDD.pmtiles` for the most recent available
  daily build (up to 14 days back). The URL used is printed and recorded in
  `public/basemap/BUILD_INFO.txt`. Override with `PMTILES_BUILD_URL=<url>` to pin a build.
  It extracts the Nairobi County bbox (36.60,-1.50 → 37.15,-1.10) at zoom ≤ 15 into
  `public/basemap/nairobi.pmtiles`.
- **`copy-basemap-assets.sh`** shallow-clones `protomaps/basemaps-assets` into `tools/` and
  copies `fonts/` (glyphs) and `sprites/` into `public/basemap/`. Without glyphs, labels do
  not render.

The `.pmtiles` file is `> 1 MB` so it is `.gitignored`; every clone rebuilds it on demand.

In production, nginx serves `/basemap/nairobi.pmtiles` with HTTP range requests enabled so
the browser only pulls the tiles it needs (K-07).

## How to run locally and offline

The app is clickable without any backend. All data is deterministic synthetic fixtures
(`apps/web/fixtures/`), served by a Vite plugin that attaches to both the dev server AND
`vite preview` (`vite-plugins/mockServer.ts`). A persistent **"Prototype — illustrative
data, not real measurements"** banner is non-dismissable while `VITE_DATA_SOURCE` is
unset or set to `fixture`.

### One-time setup (needs internet)

```sh
cd apps/web
npm install        # install dependencies
npm run basemap    # fetch the pmtiles CLI and the Protomaps daily build, extract Nairobi
```

`npm run basemap` is the only step that needs internet; it writes
`public/basemap/nairobi.pmtiles` plus `public/basemap/fonts/` and `public/basemap/sprites/`
into the gitignored `public/basemap/` folder.

### Everyday use

```sh
npm run dev        # hot-reload dev server on http://localhost:5173
npm run build      # production bundle in dist/
npm run preview    # serve dist/ on http://localhost:4173 (mock attached)
```

After `npm run basemap` once, the three commands above work with **no internet**: the
basemap, glyphs, sprites, fixtures and mock all live in this repo or in `public/basemap/`.

### Role switcher and lens

- The header has a **Role** dropdown (Viewer / Analyst). Default Viewer — the mock strips
  held findings from `/api/v1/entities/{id}?role=viewer`, so Viewer sees no trace of them.
  Switch to Analyst to see the "Not visible to other users until published" banner on
  held findings.
- The header has a **Lens** dropdown. Only `County planner` ships in v1. Switching the
  lens recolours the map (new tile URL) but never changes the panel's variable scores
  (A-15, US-301 P0 check, enforced by `EntityPanel.lensInvariance.test.tsx`).

### Playwright smoke test

```sh
npm run test:e2e
```

Builds the app, serves it with `vite preview`, and runs `e2e/smoke.spec.ts`: banner is
present, panel for `wp-002` shows coverage + confidence, published finding is visible,
zero in-app console errors. Missing `/basemap/*` 404s are tolerated so a fresh clone
without `npm run basemap` still passes.

### Pointing the frontend at the real Laravel API

When A-06 lands, set:

```sh
VITE_API_BASE_URL=https://api.staging.navuuna.example/api/v1
VITE_OPENAPI_URL=https://api.staging.navuuna.example/api/v1/openapi.json
VITE_DATA_SOURCE=api
```

Then `npm run gen:api` regenerates `src/api/schema.d.ts` from the live spec, the mock
becomes dead code at build time (its plugin lives in `vite-plugins/`, outside the client
bundle), and the illustrative-data banner disappears.

## Where the real API hooks in

The frontend is built against an OpenAPI draft (`docs/contracts/openapi.draft.yaml`). When
the Laravel API is up, point `VITE_OPENAPI_URL` at `/api/v1/openapi.json` and rerun
`npm run gen:api` — the generated types replace the fixture types; no hand-written type
survives.
