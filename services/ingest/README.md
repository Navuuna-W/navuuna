# services/ingest/

The ingest service (Bible §14.1): everything that brings data into Navuuna. It is the only
writer of `core.sources`, `core.entities`, `raw.*` and `records.*`, through the `nv_ingest`
database role (ADR-004a).

- `sources/` — the source catalogue (D-06). `source_catalogue.py` lists every Bible §7.1
  source with its licence and attribution text; each entry becomes one `core.sources` row.
  The map footer and the API read attribution from those rows (NFR-09), so this file is the
  one place that text is written.
- `tests/` — pytest; `tests/sources/` mirrors `sources/`.

Scrapers, parsers, aligners, earth-observation and vector loaders arrive with their own tasks
(D-05, D-07, D-13, D-15, D-16).

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
