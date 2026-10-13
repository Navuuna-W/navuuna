# data/

Scripts that prepare downloaded data for the ingest service (Bible §14.1, D1.3). **No data
lives here**: downloads and the files made from them stay outside the repository (Bible §14.3,
no data files over 1 MB).

- `extract_base_layers.sh` — cuts the Nairobi base layers (county, wards, water points, roads,
  buildings) out of the downloads, one GeoJSON-lines file per layer (work pack D-05).
- `osmconf.ini` — tells `ogr2ogr` which OpenStreetMap tags to keep.

## 1. Download (once, or when you want newer data)

Put these three in one folder, for example `~/navuuna-data/downloads`:

| File | From | Licence |
|---|---|---|
| `kenya-latest.osm.pbf` (about 350 MB) | https://download.geofabrik.de/africa/kenya.html | ODbL 1.0 |
| `ken_admin1.geojson` | HDX `cod-ab-ken`, inside `ken_admin_boundaries.geojson.zip` | CC BY-IGO |
| `Kenya wards.shp` and the files beside it | HDX `kenya-admin-level-3-wards`, inside `Kenya_Wards.zip` | CC BY |

The ward file is not an official layer: FAO Kenya digitised it from IEBC maps. HDX's official
boundary set stops at sub-county. Note the date in the name of the Geofabrik file you were
given (for example `kenya-261012` is 12 Oct 2026); the loader asks for it.

## 2. Extract

Needs Docker; `ogr2ogr` runs from the GDAL image, so nothing else is installed.

    data/extract_base_layers.sh ~/navuuna-data/downloads ~/navuuna-data/layers

## 3. Load

    cd services/ingest
    python -m sources.register_sources
    python -m base_layers.load_all ~/navuuna-data/layers 2026-10-12

Both need `NV_INGEST_DATABASE_URL` (see `services/ingest/README.md`). Running them again is
safe: entities keep their IDs, and an entity missing from a newer extract is retired, never
deleted.

## Test

The script has no tests of its own. Its output is checked by loading it: the load refuses an
empty layer, a feature without an ID and a repeated ID. After a load, the Done check of D-05 is:

    SELECT count(*) FROM core.entities WHERE entity_type = 'area';   -- more than 80
    SELECT count(*) FROM core.entities;                              -- more than 5,000
