# Checks the source catalogue against Bible §7.1 and against what core.sources will accept,
# so a missing source or a blank credit fails here and not when the rows are loaded.

import pytest
from pydantic import ValidationError

from sources.source_catalogue import LICENCE_NOT_CONFIRMED, SOURCE_CATALOGUE
from sources.source_definition import SourceDefinition
from sources.source_kind import SourceKind

# One line per row of the Bible §7.1 table. A row that names several publishers is split
# into one source per publisher, so a document can point at who published it.
SOURCE_NAMES_FROM_BIBLE_7_1 = {
    "OpenStreetMap (Geofabrik Kenya extract)",
    "Sentinel-2 L2A (Copernicus)",
    "Digital Earth Africa",
    "GRID3 Kenya",
    "KNBS / IEBC boundaries (HDX)",
    "CHIRPS rainfall",
    "Water Services Regulatory Board (WASREB)",
    "Nairobi City Water and Sewerage Company",
    "Nairobi City County water department",
    "Kenya National Highways Authority (KeNHA)",
    "Kenya Urban Roads Authority (KURA)",
    "Nairobi City County roads department",
    "Kenya Power outage notices",
    "People's own data",
}

# Not a row of Bible §7.1: its boundary set on HDX stops at sub-county, so the wards come from
# a second HDX data set with its own licence and credit (work pack D-05).
SOURCE_NAMES_ADDED_FOR_D_05 = {"Kenya ward boundaries (FAO, HDX)"}

# The kinds the CHECK constraint on core.sources.kind allows.
KINDS_THE_TABLE_ACCEPTS = {"scrape", "raster", "vector", "upload", "community"}


def test_every_bible_7_1_source_has_a_catalogue_entry() -> None:
    catalogue_names = {source.name for source in SOURCE_CATALOGUE}

    assert catalogue_names == SOURCE_NAMES_FROM_BIBLE_7_1 | SOURCE_NAMES_ADDED_FOR_D_05


def test_source_names_are_unique() -> None:
    catalogue_names = [source.name for source in SOURCE_CATALOGUE]

    assert len(catalogue_names) == len(set(catalogue_names))


def test_every_source_has_licence_and_attribution_text() -> None:
    for source in SOURCE_CATALOGUE:
        assert source.licence.strip(), source.name
        assert source.attribution.strip(), source.name


def test_source_kinds_match_the_kinds_the_table_accepts() -> None:
    source_kinds = {kind.value for kind in SourceKind}

    assert source_kinds == KINDS_THE_TABLE_ACCEPTS


def test_only_the_phase_2_energy_source_is_inactive() -> None:
    inactive_names = [source.name for source in SOURCE_CATALOGUE if not source.is_active]

    assert inactive_names == ["Kenya Power outage notices"]


def test_only_grid3_still_has_an_unconfirmed_licence() -> None:
    unconfirmed_names = {
        source.name for source in SOURCE_CATALOGUE if source.licence == LICENCE_NOT_CONFIRMED
    }

    assert unconfirmed_names == {"GRID3 Kenya"}


def test_source_without_attribution_is_rejected() -> None:
    with pytest.raises(ValidationError):
        SourceDefinition(
            name="A source with no credit",
            kind=SourceKind.VECTOR,
            licence="Open Database Licence (ODbL) 1.0",
            attribution="",
        )
