# Fresh UI — the design `apps/web` now builds to

What this file is: the visual system and screen inventory of the redesigned frontend, and the
plan for landing it on `main` in small PRs. It sits on top of [`DESIGN.md`](DESIGN.md) (the
measured reference) and [`DESIGN-mapping.md`](DESIGN-mapping.md) (what we take and what our rules
change). Where this file and `DESIGN.md` disagree on **radius, shadow or motion**, this file
wins — that is the one deliberate departure. Colour, the score ramp, type families and every
product rule in `apps/web/CLAUDE.md` are unchanged.

The reference implementation is the local branch `feat/web-fresh-ui` (one commit on top of
`dbfaa39`). It passed typecheck, lint, 140 unit tests, `check:tokens`, the build and all six
Playwright specs locally; it has never run in CI. It is a source to copy from, **never merged
as-is** — see §8.

---

## 1 · Tokens (`src/styles/tokens.css`)

Unchanged: every colour, the `b1`–`b4` ramp, `ca` and the `p` pattern colour, both font families,
the type scale. `npm run check:tokens` re-measures all of them, plus the two new surfaces below.

| Token | Value | Use |
|---|---|---|
| `--color-card` | `#fbfcfa` | The card everything floats on: rail, map card, Passport, page cards |
| `--color-hover` | `#eef3ef` | Row and ghost-button hover on a card |
| `--radius-chip` | 8px | gate / guard tags, IDs |
| `--radius-control` | 12px | list rows, segmented items |
| `--radius-field` | 14px | inputs, selects, textareas |
| `--radius-tile` | 16px | tiles inside a card: dates, notices, variable rows |
| `--radius-card` | 22px | every card |
| `--radius-sheet` | 28px | phone bottom sheets (top corners) |
| `--radius-pill` | 999px | buttons, nav, badges, switches |
| `--shadow-card` | 2-layer, ink at 5–7 % | resting cards |
| `--shadow-float` | ink 10 % | glass controls, account chip |
| `--shadow-pop` | ink 18 % | tooltips, menus, hover lift |
| `--shadow-sheet` | upward, ink 16 % | bottom sheets |

Elevation is light, never a dark outline: a card is `bg-card rounded-card shadow-card` with no
border. `--color-line` (the 3:1 border) stays for any boundary that must carry meaning alone.

## 2 · Components (`src/ui`)

| Component | File | Notes |
|---|---|---|
| Icon | `Icon.tsx` | Inline stroke icons, `aria-hidden`; the control carries the label |
| Button, IconButton, ButtonLink | `Button.tsx` | Pills; `primary` (ink) · `secondary` (sunken) · `ghost` · `quiet` (white + shadow). IconButton requires `label` |
| Card, Eyebrow | `Card.tsx` | `as` keeps landmarks (`aside`, `section`, `article`) |
| Badge | `Badge.tsx` | `partly` = dashed outline, `cannot` = soft stripes — shape, not hue |
| Notice | `Notice.tsx` | `info` · `notice` · `error` (system errors only) · `quiet` · `partly` |
| Switch | `Switch.tsx` | `role="switch"`, whole row is the 44 px target |
| SegmentedControl | `SegmentedControl.tsx` | Pressed buttons in a group; white thumb on sunken track |
| Skeleton, EmptyState, ErrorState | `States.tsx` | The one look for loading / nothing / failed (A-24) |
| BottomSheet | `BottomSheet.tsx` | Modal phone sheet: backdrop, handle, Esc, focus on open |

## 3 · Motion (`src/styles/motion.css`)

One ease, `--ease-spring: cubic-bezier(.2,.8,.2,1)`. Every animation is decoration and
`index.css` switches all of it off under `prefers-reduced-motion`.

| Utility | What moves |
|---|---|
| `animate-rise` (+ `stagger-1…4` or an inline delay) | Cards and rows enter: fade + 10 px rise |
| `animate-slide-in` | Passport card enters from the right (md+) |
| `animate-sheet-up` | Phone sheets slide up |
| `animate-pop` | Tooltips and menus |
| `animate-grow` | Coverage and confidence meters fill from the left |
| `animate-pulse-ring` | The selected place's ping on the map |
| `pressable` | Every button: lift 1 px on hover, scale .97 on press |
| `disclosure` | Variable rows open with a grid-rows height animation |
| `skeleton` | Shimmer for loading blocks |
| `glass` | Frosted fill for anything floating over the map |

## 4 · Layout and breakpoints

The shell (`App.tsx`) fills the viewport: banner → header → routed page (scrolls) → attribution
footer. Cards sit on `--color-page` with 12–16 px gutters.

| Width | Map screen |
|---|---|
| < 768 | Map full width. Passport = bottom sheet (58 % / 92 % height, grab handle toggles) carrying its own attribution line. Rail = BottomSheet from the map's Layers button. Nav pills scroll sideways |
| 768–1279 | Map + Passport card (360 px, 400 px from 1024). Rail still behind the Layers button |
| ≥ 1280 | Rail card (288 px) + map + Passport (400 px) |

Review: queue and decision side by side from 1024; on a phone the queue, then the decision with
a back link. Finding and Evidence pages: centred, max 1024 / 1152 px.

## 5 · Screens

| Screen | Route | File |
|---|---|---|
| S1 map + rail | `/` | `routes/MapRoute.tsx`, `map/*` |
| S2 Passport | `/?entity=` | `panel/*` |
| S3 finding | `/places/:entityId/findings/:findingId` | `routes/FindingRoute.tsx`, `findings/*` |
| S4 review queue | `/review` | `routes/ReviewRoute.tsx`, `review/ReviewQueue.tsx` |
| S5 review decision | `/review/:flagId` | `review/ReviewDetail.tsx`, `review/ReviewActions.tsx` |
| S7 sign in | `/sign-in` | `routes/SignInRoute.tsx`, `ui/useSession.ts` |
| Evidence and limits | `/evidence` | `routes/EvidenceRoute.tsx` |
| Not found (also a non-reviewer at `/review`) | `*` | `routes/NotFoundRoute.tsx` |

Not built: S6 (needs its PRD excerpt) and everything in `DESIGN-mapping.md` §3.

## 6 · The map

- Water points: dots below z11, **drop pins** from z11 (`map/style/pins.ts`, drawn once per
  `colour_class` with `map.addImage`). `p` = band fill + light hatch, `ca` = neutral stripes + "?".
  No number on any pin. Unknown class → the `ca` pin.
- Roads: a white casing under the three existing road layers.
- Selected place: larger pin on top (`water-points-selected`, filter by id) + a pulsing ring.
- Hover: a glass tooltip from `map/tooltipText.ts` — score only with coverage and confidence.
- Controls: glass zoom / fit / layers; MapLibre scale bar restyled; "How to read this map" card,
  dismissal kept in localStorage behind try/catch.
- Layer switches hide modules through `?hide=` (A-14). A failed entity tile shows "Places
  couldn't load" with Try again; an aborted tile is not a failure.
- No basemap on this machine (fresh clone) → a plain land-colour style, so places still draw.

## 7 · Rules this design must keep — checked by tests

No score without coverage + confidence (passport rows, tooltip) · not measured = reason · held
findings invisible to viewers (panel, finding page, review route) · lens changes colours only ·
one ramp, patterns for `p`/`ca` · permanent attribution (footer, and inside the phone sheet) ·
no invented copy — new strings live in `src/copy/labels.ts` or a screen's own copy file.

## 8 · Landing plan

Each row is one PR off `origin/main`, ≤ 400 changed lines (the generated `schema.d.ts` aside),
built by copying the listed files from `feat/web-fresh-ui`
(`git checkout feat/web-fresh-ui -- <paths>`). Where a row lists a shared file (`App.tsx`,
`MapView.tsx`, `AppHeader.tsx`), take only that row's part of it. Each PR adds tests for what it
lands.

| Row | Lands | Files |
|---|---|---|
| F1 `[api]` | Contract for Core's `/flags*` | `docs/contracts/openapi.draft.yaml`, `src/api/schema.d.ts` (gen:api) |
| F2 | This file + DESIGN.md pointer | `docs/design/FRESH-UI.md`, `docs/design/DESIGN.md` |
| F3 | Tokens + motion | `styles/tokens.css`, `styles/motion.css`, `index.css`, `scripts/check-design-tokens.mjs` |
| F4 | Primitives A | `ui/Icon`, `ui/Button`, `ui/Card`, `ui/Badge`, `ui/Notice` |
| F5 | Primitives B | `ui/Switch`, `ui/SegmentedControl`, `ui/States`, `ui/BottomSheet` |
| F6 | Shell + header (A-12) | `ui/dataSource`, `ui/useRole`, `ui/useCurrentUser`, `ui/AppHeader` (no account chip yet), `ui/RoleSwitch`, `ui/IllustrativeBanner`, `ui/AttributionFooter`, `routes/NotFoundRoute`, `App.tsx` (shell; footer moves out of MapRoute) |
| F7 | Sign in S7 (A-10) | `ui/useSession`, `api/client.ts`, `ui/AccountChip`, `routes/SignInRoute`, header + route wiring |
| F8 | Pins, casing, basemap fallback (A-13) | `map/style/pins.ts` (+test), `map/style/layers.ts`, `map/style/baseStyle.ts`, `MapView` image + style part |
| F9 | Tooltip + pointer | `map/tooltipText.ts` (+test), `map/MapTooltip`, `map/useMapPointer`, `MapView` part |
| F10 | Selection, tiles, controls (A-24 retry) | `map/useSelectedPlace`, `map/useEntityTiles`, `map/entitiesTileUrl`, `map/MapControls`, `MapView` final |
| F11 | Rail + URL filters (A-12, A-14) | `map/MapRail`, `map/Legend`, `map/LensSelector`, `map/HowToRead`, `map/useMapFilters` (+test), `routes/MapRoute` |
| F12 | Passport frame (A-16) | `panel/PassportFrame`, `PassportHeader`, `PassportSkeleton`, `LazyEntityPanel`, `EntityPanel`, `copy/labels.ts` |
| F13 | Passport rows (A-17) | `panel/VariableRow`, `SubVariableAccordion`, `Bars`, `Badges` |
| F14 | Finding page S3 (A-18) | `findings/findingBanner`, `FindingDetail`, `FindingCard`, `routes/FindingRoute`, route |
| F15 `[api]` | Flags mock + client hooks | `vite-plugins/mockFlags.ts`, `mockServer.ts`, `review/useFlags`, `review/reviewCopy` |
| F16 | Review queue S4 (A-22) | `review/ReviewQueue`, `routes/ReviewRoute` (queue + empty detail), route |
| F17 | Review decision S5 (A-22) | `review/ReviewActions` (+test), `review/ReviewDetail`, ReviewRoute wiring |
| F18 | Evidence and limits | `routes/EvidenceRoute`, `routes/ReviewFlow`, route |
| F19 | Accessibility + 390 px pass (A-25) | axe + keyboard walk of every screen; fixes |

B1c (the visual-regression harness, local branch `test/web-visual-regression-harness`) lands
after F13, with baselines captured from the fresh UI rather than the old one.
