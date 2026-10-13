# The source catalogue: every place Navuuna's v1 data comes from (Bible §7.1), with its licence
# and attribution text. D-06 loads this list into core.sources; loaders then find their source
# row by name, and the map footer and API read the attribution from those rows (NFR-09).

from sources.source_definition import SourceDefinition
from sources.source_kind import SourceKind

# Used where the publisher states no open licence on its site. We read only what is public
# without a login (Bible §7.2), and say so; we never claim a licence that was not given.
LICENCE_PUBLIC_NO_OPEN_LICENCE = "Public document; no open licence stated by the publisher"

# Used where we could not read the licence from the publisher's own page (checked 13 Oct 2026).
# Confirm it and replace this text before the first load from that source.
LICENCE_NOT_CONFIRMED = "Not confirmed; check the dataset page before the first load"

OPEN_DATA_SOURCES = (
    SourceDefinition(
        name="OpenStreetMap (Geofabrik Kenya extract)",
        kind=SourceKind.VECTOR,
        base_url="https://download.geofabrik.de/africa/kenya.html",
        licence="Open Database Licence (ODbL) 1.0",
        attribution="© OpenStreetMap contributors",
    ),
    SourceDefinition(
        name="Sentinel-2 L2A (Copernicus)",
        kind=SourceKind.RASTER,
        base_url="https://dataspace.copernicus.eu/",
        licence="Copernicus Sentinel data terms: free and open, attribution required",
        attribution="Contains modified Copernicus Sentinel data",
    ),
    SourceDefinition(
        name="Digital Earth Africa",
        kind=SourceKind.RASTER,
        base_url="https://www.digitalearthafrica.org/",
        licence="Creative Commons Attribution 4.0 (CC BY 4.0)",
        attribution="Digital Earth Africa",
    ),
    SourceDefinition(
        name="GRID3 Kenya",
        kind=SourceKind.VECTOR,
        base_url="https://grid3.org/",
        licence=LICENCE_NOT_CONFIRMED,
        attribution="GRID3: CIESIN, Columbia University and Novel-T",
    ),
    SourceDefinition(
        name="KNBS / IEBC boundaries (HDX)",
        kind=SourceKind.VECTOR,
        base_url="https://data.humdata.org/dataset/cod-ab-ken",
        licence="Creative Commons Attribution for Intergovernmental Organisations (CC BY-IGO)",
        attribution="IEBC and KNBS, via the Humanitarian Data Exchange (HDX)",
    ),
    # The HDX set above stops at sub-county, so the wards come from this separate HDX data set.
    # HDX states: digitised by FAO Kenya from IEBC PDF maps; "not an official administrative
    # layer from the authorities". The attribution says so, because the map footer shows it.
    SourceDefinition(
        name="Kenya ward boundaries (FAO, HDX)",
        kind=SourceKind.VECTOR,
        base_url="https://data.humdata.org/dataset/kenya-admin-level-3-wards",
        licence="Creative Commons Attribution International (CC BY)",
        attribution=(
            "Ward boundaries digitised by FAO Kenya from IEBC maps, via the Humanitarian "
            "Data Exchange (HDX); not an official boundary layer"
        ),
    ),
    SourceDefinition(
        name="CHIRPS rainfall",
        kind=SourceKind.RASTER,
        base_url="https://www.chc.ucsb.edu/data/chirps",
        licence="Public domain: the Climate Hazards Center has waived copyright",
        attribution="CHIRPS: Funk et al. 2015, Climate Hazards Center, UC Santa Barbara",
    ),
)

WATER_DOCUMENT_SOURCES = (
    SourceDefinition(
        name="Water Services Regulatory Board (WASREB)",
        kind=SourceKind.SCRAPE,
        base_url="https://wasreb.go.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Water Services Regulatory Board (WASREB)",
    ),
    SourceDefinition(
        name="Nairobi City Water and Sewerage Company",
        kind=SourceKind.SCRAPE,
        base_url="https://www.nairobiwater.co.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Nairobi City Water and Sewerage Company",
    ),
    SourceDefinition(
        name="Nairobi City County water department",
        kind=SourceKind.SCRAPE,
        base_url="https://nairobi.go.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Nairobi City County Government",
    ),
)

ROAD_DOCUMENT_SOURCES = (
    SourceDefinition(
        name="Kenya National Highways Authority (KeNHA)",
        kind=SourceKind.SCRAPE,
        base_url="https://kenha.co.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Kenya National Highways Authority (KeNHA)",
    ),
    SourceDefinition(
        name="Kenya Urban Roads Authority (KURA)",
        kind=SourceKind.SCRAPE,
        base_url="https://kura.go.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Kenya Urban Roads Authority (KURA)",
    ),
    SourceDefinition(
        name="Nairobi City County roads department",
        kind=SourceKind.SCRAPE,
        base_url="https://nairobi.go.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Nairobi City County Government",
    ),
)

OTHER_SOURCES = (
    # The energy module is Phase 2 (Bible §7.1), so the row exists but nothing loads from it.
    SourceDefinition(
        name="Kenya Power outage notices",
        kind=SourceKind.SCRAPE,
        base_url="https://www.kplc.co.ke/",
        licence=LICENCE_PUBLIC_NO_OPEN_LICENCE,
        attribution="Kenya Power and Lighting Company",
        is_active=False,
    ),
    SourceDefinition(
        name="People's own data",
        kind=SourceKind.COMMUNITY,
        licence="Shared with Navuuna under the consent each contributor gave",
        attribution="Community contributors",
    ),
)

SOURCE_CATALOGUE = (
    OPEN_DATA_SOURCES + WATER_DOCUMENT_SOURCES + ROAD_DOCUMENT_SOURCES + OTHER_SOURCES
)
