# ADR-002 — React 19 + TypeScript for `apps/web`

- **Number:** 002
- **Status:** Accepted
- **Decider:** Austine (Access — owns `apps/web`, Bible §12)
- **Refs:** DEC-03, PRD R8, Bible §8.1, work pack Austine A-01

## Context

`apps/web` is the Navuuna frontend: one map, one entity panel, one findings view, one review
queue, one sign-in. It drives MapLibre GL JS directly (ADR-005 — the tile source is MVT, no
wrapper library), fetches scored data from the Laravel API over an OpenAPI-generated client,
and listens on Laravel Reverb for live updates. Three people build the whole platform; the
frontend is owned by one of them.

Bible v1.1 §8.1 locks the backend stack but leaves the frontend framework open — PRD R8 says
*"if undecided, the Signal owner picks."* The choice between Vue 3 and React was carried as
DEC-03.

Both frameworks:

- Drive `maplibre-gl@4` directly (no `vue-maplibre-gl` / `react-map-gl` wrapper, Bible §8).
- Have first-party Laravel Echo bindings via `pusher-js`.
- Compose with Tailwind v4 the same way (CSS, not component library).
- Have mature TypeScript strict-mode support.

The one tie-breaker is which stack the single person who writes the frontend ships fastest in.
That is React, so Vue 3 is not the right call for this team on this timeline.

## Decision

**React 19 + TypeScript (strict, `noUncheckedIndexedAccess`, `noImplicitOverride`), built by
Vite 5.** The access-layer stack is fixed in this ADR so it is not reopened mid-build:

| Concern | Choice | Why |
|---|---|---|
| Framework | **React 19** | Fastest-to-ship for this author. Concurrent rendering + the new `use()` hook simplify the data-fetch flow. |
| Language | **TypeScript, strict** | Bible §14.5 forbids `any`. Generated API types mean the compiler is our contract check. |
| Bundler | **Vite 5** | Dev server for the mock MVT/API routes; small production bundle; no Webpack config to maintain. |
| Routing | **React Router 6** | URL holds lens, filters, viewport, selected entity (A-14). The URL is the authoritative state. |
| Server state | **TanStack Query 5** | Caches by key, retries, invalidates on `score.updated` from Reverb. Replaces hand-rolled hooks. |
| UI state | **Zustand 5** — UI only | Panel open/closed, dialog state. Anything the URL can hold goes in the URL instead. |
| Map | **MapLibre GL JS 4.x, used directly** | ADR-005 and Bible §8. A move to 5.x needs a new ADR. |
| Tiles | **`pmtiles` protocol + Protomaps basemap, self-hosted** | A-04. The map renders offline from `/basemap/nairobi.pmtiles`; no third-party host at runtime. |
| API types | **`openapi-typescript` → `openapi-fetch`** | Types are generated from the Laravel OpenAPI spec (A-06). Hand-writing a request type is a merge-blocker. |
| Real-time | **`laravel-echo` + `pusher-js`** against Reverb | Installed when A-21 lands; not installed yet. |
| Styling | **Tailwind v4** via `@tailwindcss/vite` | No PostCSS config file; one Vite plugin. |
| Lint | **ESLint 9 flat config** (`eslint.config.js`), typescript-eslint strict, react-hooks, jsx-a11y | Flat config is the current standard; the legacy `.eslintrc` format is retired for new projects. |
| Format | **Prettier 3** | One formatter decision, zero argument. |
| Unit tests | **Vitest + Testing Library + jsdom** | Same runner as Vite; fast enough to run per-file in watch mode. |
| E2E tests | **Playwright** | A-28 7-Oct-path check and the lens-switch invariance assertion. |

### What we rejected

- **Vue 3.** Equivalent in every way the Bible cares about; the sole tie-breaker (shipping
  speed) goes to React for this author.
- **Next.js, Remix, SvelteKit.** The frontend is a session-authenticated SPA against a
  separate Laravel API on the same origin. We do not need server-side rendering, server
  actions or route-level loaders. A Vite SPA is one less deploy target to maintain.
- **A MapLibre wrapper library** (`react-map-gl`, `maplibre-react-components`). Bible §8 and
  ADR-005 both say the map library is driven directly. Wrappers chase the MapLibre API with
  a lag and would hide the tile layer configuration we need to control precisely.

## Consequences

- `apps/web` scaffolds with the dependency list above. Exact versions are pinned in
  `apps/web/package.json`; a bump of any dependency to a new major version is a PR, not a
  silent `npm update`.
- `src/api/` holds generated types and the `openapi-fetch` client only. **Hand-writing an API
  type is a review-blocker** (NFR-02).
- The `pmtiles://` protocol is registered once, in `src/map/registerPmtiles.ts`, before any
  map is constructed. Nothing else loads a `.pmtiles` file.
- `laravel-echo` and `pusher-js` are added when A-21 lands (not in the first scaffold PR), so
  the dependency tree stays small until there is a reason to grow it.
- A move to React 20, MapLibre 5 or TanStack Query 6 needs a new ADR referencing this one.

Refs: A-01, DEC-03, PRD R8
