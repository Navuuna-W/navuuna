# Loads every base layer into core.entities (work pack D-05). Run it after
# data/extract_base_layers.sh has written the layer files:
#     python -m base_layers.load_all <layer folder> <OSM extract date, e.g. 2026-10-12>
# It is safe to run again: entities keep their IDs, and a newer extract retires what it lost.

import os
import sys
from collections.abc import Callable
from datetime import date
from pathlib import Path

from pydantic import BaseModel, ConfigDict

from base_layers.area_entities import (
    NAIROBI_COUNTY_LAYER,
    NAIROBI_WARDS_LAYER,
    build_county_entities,
    build_ward_entities,
)
from base_layers.assign_parent_areas import assign_parent_areas
from base_layers.building_entities import OSM_BUILDINGS_LAYER, build_building_entities
from base_layers.entity_row import EntityRow
from base_layers.layer_file import LayerFeature, read_layer_file
from base_layers.load_base_layer import BaseLayer, BaseLayerLoadError, load_base_layer
from base_layers.road_entities import OSM_ROADS_LAYER, build_road_entities
from base_layers.water_point_entities import OSM_WATER_POINTS_LAYER, build_water_point_entities
from database.connection import DatabaseConnection, DatabaseSettingsError, connect_to_database

# The `name` of each layer's row in core.sources (sources/source_catalogue.py).
COUNTY_SOURCE_NAME = "KNBS / IEBC boundaries (HDX)"
WARDS_SOURCE_NAME = "Kenya ward boundaries (FAO, HDX)"
OSM_SOURCE_NAME = "OpenStreetMap (Geofabrik Kenya extract)"

# The dates the two boundary data sets carry on HDX. They change only when a new boundary
# file is downloaded, so they live here and not on the command line.
COUNTY_BOUNDARY_DATE = date(2019, 10, 31)
WARDS_BOUNDARY_DATE = date(2016, 1, 1)

EXIT_OK = 0
EXIT_ERROR = 1
EXPECTED_ARGUMENT_COUNT = 2
USAGE = "usage: python -m base_layers.load_all <layer folder> <OSM extract date YYYY-MM-DD>"

EntityBuilder = Callable[[list[LayerFeature]], list[EntityRow]]

# Which mapping file turns each layer's features into entities.
ENTITY_BUILDERS: dict[str, EntityBuilder] = {
    NAIROBI_COUNTY_LAYER: build_county_entities,
    NAIROBI_WARDS_LAYER: build_ward_entities,
    OSM_WATER_POINTS_LAYER: build_water_point_entities,
    OSM_ROADS_LAYER: build_road_entities,
    OSM_BUILDINGS_LAYER: build_building_entities,
}


class LayerPlan(BaseModel):
    """One layer to load: its files, its source, its date and where its parents are."""

    model_config = ConfigDict(frozen=True)

    layer_name: str
    # File names inside the layer folder. Water points have two: OSM maps some as a node
    # and some as an outline, and the extract script writes one file for each.
    file_names: tuple[str, ...]
    source_name: str
    extract_date: date
    # The layer whose areas become this layer's parents; None for the county, which has none.
    parent_layer_name: str | None


def plan_layers(osm_extract_date: date) -> list[LayerPlan]:
    """Return the five base layers in load order: areas first, because the rest link to them."""
    return [
        LayerPlan(
            layer_name=NAIROBI_COUNTY_LAYER,
            file_names=("nairobi_county.geojsonl",),
            source_name=COUNTY_SOURCE_NAME,
            extract_date=COUNTY_BOUNDARY_DATE,
            parent_layer_name=None,
        ),
        LayerPlan(
            layer_name=NAIROBI_WARDS_LAYER,
            file_names=("nairobi_wards.geojsonl",),
            source_name=WARDS_SOURCE_NAME,
            extract_date=WARDS_BOUNDARY_DATE,
            parent_layer_name=NAIROBI_COUNTY_LAYER,
        ),
        LayerPlan(
            layer_name=OSM_WATER_POINTS_LAYER,
            file_names=("osm_water_point_nodes.geojsonl", "osm_water_point_outlines.geojsonl"),
            source_name=OSM_SOURCE_NAME,
            extract_date=osm_extract_date,
            parent_layer_name=NAIROBI_WARDS_LAYER,
        ),
        LayerPlan(
            layer_name=OSM_ROADS_LAYER,
            file_names=("osm_roads.geojsonl",),
            source_name=OSM_SOURCE_NAME,
            extract_date=osm_extract_date,
            parent_layer_name=NAIROBI_WARDS_LAYER,
        ),
        LayerPlan(
            layer_name=OSM_BUILDINGS_LAYER,
            file_names=("osm_buildings.geojsonl",),
            source_name=OSM_SOURCE_NAME,
            extract_date=osm_extract_date,
            parent_layer_name=NAIROBI_WARDS_LAYER,
        ),
    ]


def load_all_layers(
    connection: DatabaseConnection, layer_folder: Path, osm_extract_date: date
) -> int:
    """Load every base layer from the folder and return how many entities were written.

    Inputs: an open connection, the folder the extract script wrote, and the date of the
    OSM extract. Each layer is its own transaction: if roads fail, the wards stay loaded.
    Raises BaseLayerLoadError or FileNotFoundError when a layer cannot be loaded.
    Implements FR-01 and Bible D1.2 / D1.3 (boundary, wards, roads, water, buildings).
    """
    loaded_entity_count = 0
    for layer_plan in plan_layers(osm_extract_date):
        loaded_entity_count += load_planned_layer(connection, layer_folder, layer_plan)
    return loaded_entity_count


def load_planned_layer(
    connection: DatabaseConnection, layer_folder: Path, layer_plan: LayerPlan
) -> int:
    """Read one layer's files, store its entities, link them to their parent areas."""
    features: list[LayerFeature] = []
    for file_name in layer_plan.file_names:
        features.extend(read_layer_file(layer_folder / file_name))
    build_entities = ENTITY_BUILDERS[layer_plan.layer_name]
    base_layer = BaseLayer(
        layer_name=layer_plan.layer_name,
        source_name=layer_plan.source_name,
        extract_date=layer_plan.extract_date,
        entity_rows=build_entities(features),
    )
    result = load_base_layer(connection, base_layer)
    if layer_plan.parent_layer_name is not None:
        assign_parent_areas(connection, layer_plan.layer_name, layer_plan.parent_layer_name)
    print(
        f"{layer_plan.layer_name}: {result.loaded_count} loaded, "
        f"{result.skipped_count} skipped (broken geometry), {result.retired_count} retired"
    )
    return result.loaded_count


def main(arguments: list[str]) -> int:
    """Load the layers named on the command line. Output: the process exit code."""
    if len(arguments) != EXPECTED_ARGUMENT_COUNT:
        print(USAGE, file=sys.stderr)
        return EXIT_ERROR
    try:
        osm_extract_date = date.fromisoformat(arguments[1])
        connection = connect_to_database(os.environ)
    except (ValueError, DatabaseSettingsError) as error:
        print(f"Cannot load base layers: {error}", file=sys.stderr)
        return EXIT_ERROR
    with connection:
        try:
            loaded_entity_count = load_all_layers(connection, Path(arguments[0]), osm_extract_date)
        except (BaseLayerLoadError, FileNotFoundError) as error:
            print(f"Cannot load base layers: {error}", file=sys.stderr)
            return EXIT_ERROR
    print(f"Loaded {loaded_entity_count} entities into core.entities")
    return EXIT_OK


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
