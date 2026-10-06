# apps/web — Navuuna frontend

React 19 + TypeScript strict + Vite 5 + Tailwind v4. See `docs/adr/002-frontend-framework.md`
for the stack and why.

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
