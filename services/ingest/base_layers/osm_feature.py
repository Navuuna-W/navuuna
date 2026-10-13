# Reads the OpenStreetMap parts every OSM mapping file needs: the stable external reference
# and the tags. The extract script in data/ writes OSM layers with ogr2ogr, and this file
# knows how ogr2ogr names the OSM ids.

from base_layers.layer_file import LayerFeature

# ogr2ogr's OSM driver puts the id of a node, an open way or a relation in `osm_id`. In its
# polygon layer a closed way gets `osm_way_id` instead, and `osm_id` is left empty.
OSM_ID_PROPERTY = "osm_id"
OSM_WAY_ID_PROPERTY = "osm_way_id"

NODE_EXTERNAL_REF_PREFIX = "osm:n"
WAY_EXTERNAL_REF_PREFIX = "osm:w"
RELATION_EXTERNAL_REF_PREFIX = "osm:r"

POINT_GEOMETRY_TYPE = "Point"
LINE_GEOMETRY_TYPE = "LineString"


class OsmFeatureError(Exception):
    """An OSM feature has no id, so it cannot become an entity."""


def build_osm_external_ref(feature: LayerFeature) -> str:
    """Return the feature's stable reference: `osm:n123`, `osm:w456` or `osm:r789`.

    Input: one feature of an OSM layer file. Output: its external reference.
    Raises OsmFeatureError when the feature carries no OSM id.
    Implements FR-02 (stable external IDs) with the formats from work pack D-05.
    """
    way_id = feature.properties.get(OSM_WAY_ID_PROPERTY)
    if way_id:
        return f"{WAY_EXTERNAL_REF_PREFIX}{way_id}"
    osm_id = feature.properties.get(OSM_ID_PROPERTY)
    if not osm_id:
        # No id means no stable reference; inventing one would break FR-02.
        raise OsmFeatureError("OSM feature has neither 'osm_id' nor 'osm_way_id'")
    geometry_type = feature.geometry.get("type")
    if geometry_type == POINT_GEOMETRY_TYPE:
        return f"{NODE_EXTERNAL_REF_PREFIX}{osm_id}"
    if geometry_type == LINE_GEOMETRY_TYPE:
        return f"{WAY_EXTERNAL_REF_PREFIX}{osm_id}"
    return f"{RELATION_EXTERNAL_REF_PREFIX}{osm_id}"


def read_osm_tags(feature: LayerFeature, tag_names: tuple[str, ...]) -> dict[str, str]:
    """Return the named tags the feature really has.

    Inputs: a feature and the tag names to look for. Output: tag name → value, holding only
    the tags that are present and not blank. A missing tag is left out, never given a
    default: the adapters report it as not measured (Bible §6.1, §7.1).
    """
    tags: dict[str, str] = {}
    for tag_name in tag_names:
        value = feature.properties.get(tag_name)
        if value is None or not str(value).strip():
            continue
        tags[tag_name] = str(value).strip()
    return tags
