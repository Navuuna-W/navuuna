# Checks the fixture file that is committed in data/fixtures/: small enough for the
# repository, the promised number of entities, and no operator names.

from pathlib import Path

FIXTURE_FILE_PATH = Path(__file__).parents[4] / "data" / "fixtures" / "nairobi_500_entities.sql"

# Bible §14.3: no data file over 1 MB in the repository.
MAX_FIXTURE_BYTES = 1_000_000
EXPECTED_ENTITY_COUNT = 500
# Every entity row carries its id as the first value, cast from text in the SELECT; a
# source row has no "osm:" or "iebc:" reference, so this prefix counts entities only.
ENTITY_REFERENCE_MARKERS = ("'osm:", "'iebc:")


def test_committed_fixture_is_under_one_megabyte() -> None:
    fixture_bytes = FIXTURE_FILE_PATH.stat().st_size

    assert fixture_bytes < MAX_FIXTURE_BYTES


def test_committed_fixture_holds_500_entities() -> None:
    fixture_lines = FIXTURE_FILE_PATH.read_text(encoding="utf-8").splitlines()

    entity_lines = [
        line
        for line in fixture_lines
        if line.startswith("    ('") and any(marker in line for marker in ENTITY_REFERENCE_MARKERS)
    ]
    assert len(entity_lines) == EXPECTED_ENTITY_COUNT


def test_committed_fixture_holds_no_operator_names() -> None:
    fixture_sql = FIXTURE_FILE_PATH.read_text(encoding="utf-8")

    assert '"operator"' not in fixture_sql
