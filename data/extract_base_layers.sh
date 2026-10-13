#!/usr/bin/env bash
# Cuts the Nairobi base layers out of the downloaded source files (work pack D-05, Bible D1.3).
# It writes one GeoJSON-lines file per layer; `python -m base_layers.load_all` in
# services/ingest then loads them into core.entities. See data/README.md for the downloads.
#
# usage: data/extract_base_layers.sh <download folder> <layer folder>
set -euo pipefail

if [ "$#" -ne 2 ]; then
    echo "usage: data/extract_base_layers.sh <download folder> <layer folder>" >&2
    exit 1
fi
DOWNLOAD_FOLDER="$(realpath "$1")"
mkdir -p "$2"
LAYER_FOLDER="$(realpath "$2")"
DATA_FOLDER="$(dirname "$(realpath "$0")")"

# File names as the publishers ship them (data/README.md says where each comes from).
OSM_EXTRACT="kenya-latest.osm.pbf"
COUNTY_BOUNDARIES="ken_admin1.geojson"
WARD_BOUNDARIES="Kenya wards.shp"

NAIROBI_COUNTY_CODE="KE047"
# West, south, east, north: a box a little larger than Nairobi county. It only keeps the
# working file small; the county outline decides what is loaded.
NAIROBI_BOUNDING_BOX="36.65 -1.45 37.11 -1.15"

# The water and sanitation tags of work pack D-05. A storage tank is checked for
# content=water later, in services/ingest/base_layers/water_point_entities.py.
WATER_TAGS="(f.amenity IN ('drinking_water', 'water_point', 'toilets')
    OR f.man_made IN ('water_well', 'water_tap', 'water_tower', 'water_works',
                      'reservoir_covered', 'wastewater_plant', 'storage_tank'))"

# ogr2ogr comes from the GDAL image, so nothing has to be installed on the machine. The
# "normal" image is needed: the "small" one has no ST_Intersects.
GDAL_IMAGE="ghcr.io/osgeo/gdal:alpine-normal-latest"

run_ogr2ogr() {
    docker run --rm --user "$(id -u):$(id -g)" --workdir /layers --env CPL_TMPDIR=/layers \
        --volume "$DOWNLOAD_FOLDER:/downloads:ro,z" --volume "$LAYER_FOLDER:/layers:z" \
        --volume "$DATA_FOLDER:/data:ro,z" "$GDAL_IMAGE" ogr2ogr "$@"
}

# Writes the features of one OSM layer that have the wanted tags and touch the county.
# Features are kept whole, never cut at the county line: a cut road can fall into several
# pieces, and a `segment` entity must be one line.
write_osm_layer() {
    local layer_file="$1" osm_layer="$2" wanted_tags="$3"
    rm -f "$LAYER_FOLDER/$layer_file"
    run_ogr2ogr -f GeoJSONSeq "/layers/$layer_file" /layers/nairobi_box.gpkg -dialect SQLite \
        -sql "SELECT f.* FROM $osm_layer AS f, county
              WHERE $wanted_tags AND ST_Intersects(f.geom, county.geom)"
}

echo "1/4 county boundary and wards"
rm -f "$LAYER_FOLDER/nairobi_county.geojsonl" "$LAYER_FOLDER/nairobi_wards.geojsonl"
run_ogr2ogr -f GeoJSONSeq /layers/nairobi_county.geojsonl "/downloads/$COUNTY_BOUNDARIES" \
    -where "adm1_pcode = '$NAIROBI_COUNTY_CODE'" -select adm1_name,adm1_pcode
# The ward file ships without a .prj file; its coordinates are longitude/latitude (EPSG:4326).
run_ogr2ogr -f GeoJSONSeq /layers/nairobi_wards.geojsonl "/downloads/$WARD_BOUNDARIES" \
    -where "FIRST_PROV = 'Nairobi'" -select IEBC_WARDS,PCODE,FIRST_DIVI -a_srs EPSG:4326

echo "2/4 OpenStreetMap features in the Nairobi box"
rm -f "$LAYER_FOLDER/nairobi_box.gpkg"
run_ogr2ogr -f GPKG /layers/nairobi_box.gpkg "/downloads/$OSM_EXTRACT" points lines multipolygons \
    -oo CONFIG_FILE=/data/osmconf.ini -spat $NAIROBI_BOUNDING_BOX
run_ogr2ogr -update -nln county /layers/nairobi_box.gpkg /layers/nairobi_county.geojsonl

echo "3/4 water points and roads"
write_osm_layer osm_water_point_nodes.geojsonl points "$WATER_TAGS"
write_osm_layer osm_water_point_outlines.geojsonl multipolygons "$WATER_TAGS"
write_osm_layer osm_roads.geojsonl lines "f.highway IS NOT NULL"

echo "4/4 buildings (the largest layer; about a minute)"
write_osm_layer osm_buildings.geojsonl multipolygons "f.building IS NOT NULL"

rm -f "$LAYER_FOLDER/nairobi_box.gpkg"
wc -l "$LAYER_FOLDER"/*.geojsonl
