# ADR-005 — Vector tiles for the map, GeoJSON for the API

- **Number:** 005
- **Date:** 20 Sep 2026 (decided) · recorded 9 Oct 2026
- **Status:** Accepted
- **Decider:** Khillon (Core — owns the database and the server, Bible §12)
- **Refs:** Bible v1.1 locked decisions ("Map delivery"), §11.1, §11.2, §12 ADR register
  (row 005, Accepted), change log 20 Sep 2026, FR-13, FR-23, NFR-03, DEC-12,
  `docs/contracts/tiles.md`, ADR-008 (A-09)

## Context

This ADR is cited in five places — `apps/web/CLAUDE.md` product rule 4,
`docs/contracts/tiles.md` (Refs and §6), Bible §11.2, the Bible's commit-message example, and
the Bible's own ADR register, which already lists it as **Accepted** — but the file was never
written. The decision is not in question; the record was missing. This is the write-up.

Two consumers want the same scored entities in incompatible shapes:

- **The map (S1, FR-13)** pans and zooms over Nairobi. It needs whatever is in the current
  viewport, at the current zoom, hundreds of times per session, in milliseconds (NFR-03).
- **Institutions (FR-23)** want a bounded, documented, machine-readable extract they can load
  into their own GIS.

One format cannot serve both. A GeoJSON `FeatureCollection` large enough to be useful to an
institution is far too large to re-fetch while panning, and a binary tile is useless to a client
that wants to read a response body.

## Decision

**Two consumers, two formats: the map uses tiles, machines use JSON.**

1. **The map renders from Mapbox Vector Tiles**, `GET /tiles/{z}/{x}/{y}.mvt`, generated in
   PostGIS with `ST_AsMVT`, binary and per-viewport, fetched automatically by MapLibre as the
   user pans. The frontend wires a MapLibre **`vector`** source, never a `geojson` source.
2. **The institutional API returns GeoJSON**, `GET /entities?module=&type=&bbox=&lens=`, with
   **`bbox` required and the result capped at 500 features** — for institutional clients, not
   for the map.
3. **A click does not grow the tile.** The frontend takes `id` from the tile and calls
   `GET /entities/{id}` for the full picture: one request, at the moment it is needed.
4. **Tile properties are limited** to `id`, `entity_type`, `module`, `lens_score`,
   `colour_class`, `coverage`. Tiles are requested hundreds of times while panning, so every
   extra field is paid for every time.
5. **Tile cache key** is `lens_id + weight_version + last_computed_at`, invalidated when a
   rollup finishes.

## Consequences

- **The map layer must be built against a `vector` source from the start.** Switching later
  means rewriting the layer, the styling and the click handling (Bible §11.2). Already done:
  `apps/web/src/map/MapView.tsx` uses `type: 'vector'` with `promoteId: 'id'` and source-layer
  `entities`.
- **Clustering must happen server-side.** MapLibre's built-in `cluster: true` is a GeoJSON
  source feature and does not apply to vector tiles (`tiles.md` §6).
- **The 500-feature cap is a contract guarantee, not a performance tweak.** `/entities` returns
  422 without a `bbox`; it is not a fallback for the map.
- **Ownership moved.** Bible §11.2 and the change log put the tile endpoint with Khillon
  (K-10). ADR-008 moves the MVT endpoint and its cache to the Access lane as **A-09**.
  `tiles.md` §9 still reads "Until K-10 ships the real tile endpoint" and is corrected in the
  contract PR.
- **One open tension, carried by DEC-12.** §11.2 limits tile properties to the six above and
  says "nothing else", but NFR-02 forbids showing a score without its confidence, and
  `lens_score` is a score. `tiles.md` §4 therefore proposes `confidence` at all zooms and
  `name` at z ≥ 14, pending Devyan's confirmation. Until DEC-12 lands the client keeps an
  NFR-02 guard that renders an error state rather than a bare `lens_score`. Resolving DEC-12
  amends the property list in §4 of the tile contract, not this ADR.

## Note on provenance

Every element of this decision is traceable in Bible v1.1: the locked-decisions table ("Vector
tiles (ST_AsMVT) for the map; GeoJSON for the institutional API"), §11.1 and §11.2 (both marked
`[NEW in 1.1 — ADR-005]`), the ADR register row, and the 20 Sep 2026 change-log entry naming
Khillon. I could not find a section headed "Open Questions Q4" in the repo copy of the Bible, so
this ADR cites the sections above instead. If that heading lives in a document outside the
repository, add it to Refs.
