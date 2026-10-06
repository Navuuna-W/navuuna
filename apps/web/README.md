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

## Running locally with no internet

Everything the app needs at runtime (basemap tiles, glyphs, sprites, synthetic data) is
served from this repo. See the "How to run locally and offline" section at the bottom of
this file (added by Step 10).

## Where the real API hooks in

The frontend is built against an OpenAPI draft (`docs/contracts/openapi.draft.yaml`). When
the Laravel API is up, point `VITE_OPENAPI_URL` at `/api/v1/openapi.json` and rerun
`npm run gen:api` — the generated types replace the fixture types; no hand-written type
survives.
