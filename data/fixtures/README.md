# data/fixtures/

The seed data for the local stack (Bible §14.11, work pack D-14). `infra/local/setup.sh`
loads every `*.sql` file here with `psql`, right after the migrations.

- `nairobi_500_entities.sql` — 500 real entities from the Nairobi base layers:

  | Entities | Count |
  |---|---|
  | Nairobi county and its wards (`area`) | 85 |
  | Water points in Lindi (Kibera) and Mabatini (Mathare) (`point`, module `water`) | 300 |
  | Roads nearest the middle of those two wards (`segment`, module `roads`) | 100 |
  | Buildings beside them (`parcel`, module `land`, which is disabled) | 15 |

  It also holds the three `core.sources` rows those entities come from.

## What to know

- **Generated, never edited by hand.** Build it again from a database that holds the full
  base layers (D-05):

      cd services/ingest
      python -m local_fixture.build_local_fixture ../../data/fixtures/nairobi_500_entities.sql

  The same load always gives the same file.
- **Safe to load twice.** A row whose `external_ref` (or source `name`) already exists is
  skipped.
- **Same ids on every laptop.** Each entity keeps the id and `external_ref` it had in the
  load the file was built from (Geofabrik Kenya extract of 12 Oct 2026).
- **No personal data.** The OpenStreetMap `operator` tag is left out because it can hold a
  person's name. Place names are kept as OpenStreetMap has them.
- **Outlines are simplified** to about 5 metres to keep the file small, so wards here are
  slightly coarser than on staging.
- **Credits.** Map data © OpenStreetMap contributors (ODbL 1.0). Ward boundaries digitised by
  FAO Kenya from IEBC maps, via HDX (CC BY); not an official boundary layer. County boundary:
  IEBC and KNBS, via HDX (CC BY-IGO).

## Test

`services/ingest/tests/local_fixture/` checks the file's size, its entity count and that it
loads; run `pytest` there.
