# Tile contract — `/tiles/{z}/{x}/{y}.mvt`

**Status:** Draft (Austine owns, Devyan + Khillon review). Lives next to
`openapi.draft.yaml` because MVT is binary, not JSON — OpenAPI cannot describe its
payload. Any change to this file is a merge-blocker on `apps/web`.

**Refs:** ADR-005, Bible §11.2, DEC-12, DEC-15, A-05, A-09.

## 1 · Endpoint

```
GET /tiles/{z}/{x}/{y}.mvt
```

Content type: `application/vnd.mapbox-vector-tile`. One tile per request. The Nairobi
basemap is served separately as PMTiles (`/basemap/nairobi.pmtiles`); this endpoint only
carries scored entities.

Response codes:

| Code | When |
|---|---|
| `200` | Tile has features. Body is the gzipped MVT. |
| `204` | Tile is empty (nothing inside its bbox). Body is empty. |
| `401` | Caller is not signed in (session auth). |
| `403` | Caller is signed in but their role may not read this layer. |
| `404` | `z/x/y` is outside the configured zoom range. |
| `429` | Caller hit the tile-endpoint rate limiter (not the API-key limiter). |

## 2 · Query parameters

| Name | Required | Values | Meaning |
|---|---|---|---|
| `lens` | optional | lens id, e.g. `county_planner` | Which lens produced `lens_score` and `colour_class`. Default is `county_planner`. |
| `module` | optional | `water`, `roads`, `land`, `energy`, `safety` | Filter to one module. Omit for all enabled modules. |
| `type` | optional | `water_point`, `road_segment`, `ward`, `parcel` | Filter to one entity type. |

Unknown parameters are ignored. Unknown lens or module → `404` (unknown resource) rather
than a silent empty tile.

## 3 · Layer

Exactly one source-layer, named **`entities`**. Every feature in the layer is a scored
entity. Three geometry types appear:

| Entity type | Geometry |
|---|---|
| `water_point` | `Point` |
| `road_segment` | `LineString` |
| `ward` | `Polygon` |

## 4 · Properties

Promoted feature ID is the entity ID (string). This exact set is served **always**:

| Property | Type | Range | Meaning |
|---|---|---|---|
| `id` | string | — | Entity ID (same as the promoted feature ID). |
| `entity_type` | string | `water_point` / `road_segment` / `ward` / `parcel` | Which type. |
| `module` | string | `water` / `roads` / `land` / … | Owning module. |
| `lens_score` | integer or null | `0..100` or `null` | Lens output for this entity. `null` iff the entity could not be assessed. |
| `colour_class` | string | see §5 | Pre-computed colour band. The client reads this directly to paint — never recomputes from `lens_score`. |
| `coverage` | number | `0.0..1.0` | Lens-level coverage (weighted share of variables that contributed). |

Proposed additions **(DEC-12 — pending Devyan's confirmation; the mock already serves
them)**:

| Property | Type | Range | Meaning | Zoom gate |
|---|---|---|---|---|
| `confidence` | number | `0.0..1.0` | Lens-level confidence. Needed because scores are never shown without their confidence (NFR-02). | all zooms |
| `name` | string | — | Short, user-facing name (e.g. "Water point 004 (Westlands)"). Hover + z ≥ 14 label. | **z ≥ 14 only** |

If DEC-12 lands, the live spec is updated and this row moves out of the "proposed"
section. Until then, servers may omit `confidence` or `name`; the client has an NFR-02
guard that falls back to an error state rather than render a bare `lens_score`.

Properties not listed here are **not** served. A ticket is required to add one.

## 5 · `colour_class` vocabulary — DEC-15

One sequential, colour-blind-safe scale. Nine possible values:

| Class | Band (lens_score) | Meaning | Visual |
|---|---|---|---|
| `b1` | 80–100 | Good | Darkest fill |
| `b2` | 60–79 | Fair | Darker fill |
| `b3` | 40–59 | Poor | Lighter fill |
| `b4` | 0–39 | Critical | Lightest fill |
| `b1p` | 80–100 | Good, partly verified | Hollow + darkest ring |
| `b2p` | 60–79 | Fair, partly verified | Hollow + darker ring |
| `b3p` | 40–59 | Poor, partly verified | Hollow + lighter ring |
| `b4p` | 0–39 | Critical, partly verified | Hollow + lightest ring |
| `ca` | — | Cannot assess | Neutral grey hollow pattern — never drawn as 0 |

Suffixes:

- **`p`** — the entity is partly verified (gate failed or coverage < 1). Visually distinct
  from the solid band so a reader never mistakes a provisional score for a full one.
- **`ca`** — the entity could not be assessed. `lens_score` is `null` for these features.

Red/amber/green is **banned** (CLAUDE.md product rule 5). Colour is never the only carrier
of meaning — pattern/stroke carry the `p` and `ca` signals too.

## 6 · Clustering

Below zoom 12, **server-side clusters** may be returned instead of individual points
when a tile would otherwise hold more than ~1,000 features (DEC-23 — 7 Oct trim). Cluster
features carry only `id`, `entity_type: 'cluster'`, `module`, and a `count` property.
They are never scored (no `lens_score`), and `colour_class` is `ca`.

MapLibre's built-in `cluster: true` is a **GeoJSON** source feature; it does not apply to
vector tiles. That is why clustering must happen server-side (ADR-005).

## 7 · Auth and rate limiting

- **Session auth.** Signed-in users only. The Reverb session cookie is attached
  automatically on the same origin; no bearer token is used.
- **Dedicated rate limiter.** The tile endpoint has its own bucket, not the 60/min
  API-key limiter. One map load fetches dozens of tiles — a shared limiter would starve
  JSON API calls. Current limit: TBD (Khillon, K-11).
- **Role filtering.** Entities are returned regardless of the caller's role; the lens
  score is identical for every role. **Held findings never appear here** (they are a
  JSON-API concern, filtered by role server-side — see
  [`openapi.draft.yaml`](openapi.draft.yaml) and A-19/DEC-08).

## 8 · Caching

Server-side cache key: `(lens_id, weight_version, last_computed_at)`. Invalidated on
`lens.applied` and `score.updated` events. **No tile cache ships for 7 Oct** (DEC-23 —
A-20 moves after 7 Oct); the current recommendation is `Cache-Control: no-store` until
the cache lands.

Clients may NOT cache tiles themselves across lens switches — the lens is in the URL,
not the headers.

## 9 · Reference implementation

Until K-10 ships the real tile endpoint, `apps/web/vite-plugins/mockServer.ts` serves
this contract against the deterministic fixtures in `apps/web/fixtures/`. The mock
attaches to **both** `configureServer` (dev) and `configurePreviewServer` (preview),
and is excluded from the production bundle. The parity test
`apps/web/vite-plugins/mockServer.contract.test.ts` fails CI if the mock's feature
properties drift from this document.
