# services/ingest/

The ingest service (Bible §14.1): everything that brings data into Navuuna. It is the only
writer of `core.sources`, `core.entities`, `raw.*` and `records.*`, through the `nv_ingest`
database role (ADR-004a).

- `sources/` — the source catalogue (D-06). `source_catalogue.py` lists every Bible §7.1
  source with its licence and attribution text; each entry becomes one `core.sources` row.
  The map footer and the API read attribution from those rows (NFR-09), so this file is the
  one place that text is written.
  `register_sources.py` writes that list into `core.sources`.
- `base_layers/` — the base layers (D-05): boundaries and OpenStreetMap layers that become
  entities in `core.entities`.
  - `layer_file.py` reads a layer file (GeoJSON lines, one feature per line).
  - `area_entities.py` turns the county and ward features into `area` entities.
  - `water_point_entities.py` turns OpenStreetMap water and sanitation features into `point`
    entities; `osm_feature.py` reads their OSM ids and tags.
  - `entity_row.py` is the shape every entity has on its way into the table.
  - `load_base_layer.py` writes a layer. A reload keeps every entity's ID (it matches on
    `external_ref`), retires entities the new file no longer has, and never deletes one.
- `database/` — `connection.py` opens the service's one connection, from `NV_INGEST_DATABASE_URL`.
- `tests/` — pytest; each folder under `tests/` mirrors the package of the same name.

The OpenStreetMap roads and buildings arrive with D-05d; the extract script, the
`raw.vectors` row and the command that loads every layer with D-05e.
Scrapers, parsers, aligners and earth observation arrive with their own tasks (D-07, D-13,
D-15, D-16).

## Adding or changing a source

Edit `sources/source_catalogue.py`. Never rename a source that already has rows: loaders find
their source by `name`. Take licence and attribution wording from the publisher's own page.
If you cannot confirm the licence, use `LICENCE_NOT_CONFIRMED`, and confirm it before the
first load from that source.

## Run and test

    cd services/ingest
    python -m venv .venv && . .venv/bin/activate
    pip install -e ".[dev]"
    pytest
    mypy --strict .
    ruff check . && ruff format --check .

Database tests are skipped unless `NV_TEST_DATABASE_URL` points at a PostgreSQL that has the
Laravel migrations applied. They act as `nv_ingest` and roll back, so they leave no rows:

    NV_TEST_DATABASE_URL=postgresql://navuuna:navuuna@127.0.0.1:15432/navuuna pytest

## Load the sources (work pack D-06)

    NV_INGEST_DATABASE_URL=postgresql://<nv_ingest login>@127.0.0.1:15432/navuuna \
        python -m sources.register_sources

It is safe to run again: a source that already has a row is updated, never duplicated.
