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

## Running locally with no internet

Everything the app needs at runtime (basemap tiles, glyphs, sprites, synthetic data) is
served from this repo. The "How to run locally and offline" section lands with Step 10.

## Where the real API hooks in

The frontend is built against an OpenAPI draft (`docs/contracts/openapi.draft.yaml`). When
the Laravel API is up, point `VITE_OPENAPI_URL` at `/api/v1/openapi.json` and rerun
`npm run gen:api` — the generated types replace the fixture types; no hand-written type
survives.
