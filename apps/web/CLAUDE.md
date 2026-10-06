# apps/web — Navuuna frontend + access layer (owner: Austine)

Navuuna scores places in Nairobi (water points, road segments, wards) on five fixed variables,
each shown with coverage and confidence, and raises findings — held for human review — where
official records disagree with observations. Institutions read the scores through lenses.

## Sources of truth — read before deciding anything

- `docs/workpacks/Navuuna_WorkPack_Austine.md` — my tasks A-01…A-33 and done-whens
- `docs/BUILD_BIBLE.md` (v1.1) — §6 engine rules, §8 stack, §11 API, §14 engineering rules
- `docs/PRD.md` (PRD-001) — screens S1–S8, copy rules §10, edge cases E1–E15, release criteria §15
- `docs/CONTEXT.md` — vocabulary (Devyan owns it; use its words once it exists)

If two sources disagree, stop and ask. Never pick silently.

## My lane — what I may change

- All of `apps/web` (the frontend is mine alone).
- In `apps/api`: `app/Http/Controllers/Api/V1`, `app/Http/Resources`, `app/Http/Requests`, `routes/api.php`,
  `app/Tiles`, `app/Lens`, `app/Broadcasting` + `routes/channels.php`, `app/Observations`, OpenAPI generation.
- The `web` job in CI, `docs/adr/002-*`, `docs/adr/008-*` (draft), `docs/contracts/*`.
- Read-only for me: `apps/api` Engine / Findings / Auth / Audit / Consumers (Khillon), `apps/api/database/migrations`
  (I may author; Khillon approves), `services/**`, `data/**` (Devyan; `services/signals/engine` is Khillon),
  `infra/**`, the rest of `.github/**` (Khillon).

## Stack (ADR-002)

TypeScript strict (no `any`) · React 19 · Vite · Tailwind v4 · React Router · TanStack Query (server state) ·
Zustand (UI state only — the URL holds lens, filters, view and selected entity) · MapLibre GL JS 4.x used
directly, no wrapper (Bible §8; a forced move to 5.x needs an ADR — tell me) · `pmtiles` protocol + Protomaps
basemap, self-hosted · `openapi-typescript` + `openapi-fetch` · `laravel-echo` + `pusher-js` (Reverb) ·
Vitest + Testing Library · Playwright · ESLint 9 flat config + Prettier.

## Product rules that are never broken

1. No score is rendered without its coverage and confidence beside it. If the API omits either, show an
   error state — never a bare number (NFR-02).
2. Unmeasured = "Not measured" + its reason. Never 0, never "—", never a default.
3. A held finding never reaches a viewer in any form: no row, count, badge, placeholder or colour.
   Only analyst/admin review screens show held findings.
4. Entities render only from the MVT vector source `/tiles/{z}/{x}/{y}.mvt` — never a GeoJSON source (ADR-005).
5. One sequential, colour-blind-safe scale — never red/amber/green. Partly verified = hatch; cannot assess =
   neutral pattern, never drawn as 0. Colour is never the only carrier of meaning.
6. Permanent, non-dismissable footer: "Contains modified Copernicus Sentinel data 2026 · © OpenStreetMap
   contributors · Digital Earth Africa · GRID3".
7. API types are generated from OpenAPI (`npm run gen:api`), never hand-written.
8. Switching lens changes map colours only — never the panel's variable scores.
9. Copy describes the gap, never a cause, intent or person. Dates "14 Sep 2026"; relative time only under 24 h;
   integer scores; units always. Never "asset", "site" or "feature" for a scored entity.

## Display conventions (some pending — keep them all in `src/copy/labels.ts` so a decision is a one-file change)

- Variables, fixed order: V1 "Is it working?" · V2 "Does the record match?" · V3 "Which way is it going?" ·
  V4 "Will its inputs hold?" · V5 "Can people reach it?" — final wording DEC-20
- Confidence words: low < 0.4 · medium 0.4–0.7 · good ≥ 0.7
- "X of Y" = measured contributors / all contributors of that variable (V1 4, V2 4, V3 6, V4 6, V5 6) — DEC-11
- `colour_class`: `b1`–`b4` for bands 80–100 / 60–79 / 40–59 / 0–39; suffix `p` = partly verified; `ca` = cannot assess — DEC-15
- Tile properties: `id, entity_type, module, lens_score, colour_class, coverage` (+ `confidence`, + `name` at z ≥ 14 if DEC-12 passes)

## Code rules

- Components < 200 lines; one concern per file; tests colocated (`*.test.ts(x)`).
- Folders: `src/map`, `src/panel`, `src/findings`, `src/review`, `src/realtime`, `src/api` (generated types +
  client only), `src/copy`, `src/ui`, `src/routes`.
- Every function that produces a number a user sees gets a unit test with a known input and known output.
- Keyboard reachable, visible focus, WCAG AA contrast.
- No TODO comments in `main` — raise an issue instead. No secrets. Never commit `.pmtiles`, glyphs, sprites or
  any file over 1 MB — scripts fetch or build them.

## Git

- Branch `type/scope-short-description` off `main` (e.g. `feat/web-basemap`); merge within 3 days.
- Conventional Commits, scope `web` or `api`; imperative summary ≤ 72 chars; body says what and why and
  names the task ID (A-xx) and FR. Examples: `feat(web): self-hosted Nairobi basemap (A-04)`,
  `docs(adr): ADR-002 choose React for apps/web`.
- One logical change per commit; never commit a failing build. PRs ≤ 400 changed lines; squash-merge.
  Reviewer: Devyan for `apps/web`, Khillon for `apps/api`. Never push or open a PR unless I say so.

## Done means (Bible §14.8)

Merged to `main`, CI green, deployed to staging, shown at the next checkpoint, tests added, docs/ADR updated.

## Commands (keep this list current)

`npm run dev` · `build` · `preview` · `typecheck` · `lint` · `test` · `test:e2e` · `gen:api` · `basemap`

## How to work with me

- Plan first. Wait for my OK before large changes, new dependencies or anything outside my lane.
- After each step: run typecheck, lint, test and build; show the results; summarise the diff in 3–5 lines.
- Blocked? Stop, say on whom (task ID), and propose the smallest workaround.
- End every session with: done (by A-ID and done-when) · blocked (on whom) · next steps.
