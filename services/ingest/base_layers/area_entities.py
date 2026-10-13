# Turns boundary features into `area` entities: the Nairobi county boundary and its wards
# (work pack D-05, Bible D1.2). Runs between layer_file.py (read) and load_base_layer.py (write).

from base_layers.entity_row import EntityRow
from base_layers.entity_type import EntityType
from base_layers.layer_file import LayerFeature

# Layer names. They are stored on every entity and in raw.vectors, so never rename one
# after its first load: the loader finds a layer's earlier entities by this name.
NAIROBI_COUNTY_LAYER = "nairobi_county"
NAIROBI_WARDS_LAYER = "nairobi_wards"

# Attribute names in the HDX files. County: "Kenya - Subnational Administrative Boundaries"
# (ken_admin1). Wards: "kenya-admin-level-3-wards" (Kenya wards.shp).
COUNTY_NAME_PROPERTY = "adm1_name"
COUNTY_CODE_PROPERTY = "adm1_pcode"
WARD_NAME_PROPERTY = "IEBC_WARDS"
WARD_CODE_PROPERTY = "PCODE"
WARD_DIVISION_PROPERTY = "FIRST_DIVI"

COUNTY_EXTERNAL_REF_PREFIX = "iebc:county:"
WARD_EXTERNAL_REF_PREFIX = "iebc:ward:"


class BoundaryFeatureError(Exception):
    """A boundary feature has no name or no code, so it cannot become an entity."""


def build_county_entity(feature: LayerFeature) -> EntityRow:
    """Turn the county boundary feature into an `area` entity.

    Input: one feature of the county layer. Output: its entity, `iebc:county:<code>`.
    Raises BoundaryFeatureError when the name or the code is missing.
    Implements FR-01 and Bible D1.2 (boundary loaded as an area).
    """
    county_code = read_required_property(feature, COUNTY_CODE_PROPERTY)
    return EntityRow(
        entity_type=EntityType.AREA,
        name=read_required_property(feature, COUNTY_NAME_PROPERTY),
        external_ref=f"{COUNTY_EXTERNAL_REF_PREFIX}{county_code}",
        geometry=feature.geometry,
        metadata={"area_kind": "county"},
    )


def build_ward_entity(feature: LayerFeature) -> EntityRow:
    """Turn one ward feature into an `area` entity.

    Input: one feature of the wards layer. Output: its entity, `iebc:ward:<code>`.
    Raises BoundaryFeatureError when the name or the code is missing.
    Implements FR-01 and Bible D1.2 (wards loaded as areas).
    """
    ward_code = read_required_property(feature, WARD_CODE_PROPERTY)
    metadata = {"area_kind": "ward"}
    division = feature.properties.get(WARD_DIVISION_PROPERTY)
    # The division is extra context; a ward without one is still a ward.
    if division:
        metadata["division"] = str(division)
    return EntityRow(
        entity_type=EntityType.AREA,
        name=read_required_property(feature, WARD_NAME_PROPERTY),
        external_ref=f"{WARD_EXTERNAL_REF_PREFIX}{ward_code}",
        geometry=feature.geometry,
        metadata=metadata,
    )


def read_required_property(feature: LayerFeature, property_name: str) -> str:
    """Return a feature attribute as text, or raise BoundaryFeatureError when it is empty."""
    value = feature.properties.get(property_name)
    if value is None or not str(value).strip():
        # No name or code means no stable ID; inventing one would break FR-02.
        raise BoundaryFeatureError(f"boundary feature has no '{property_name}'")
    return str(value).strip()
