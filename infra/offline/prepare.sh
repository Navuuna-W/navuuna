#!/usr/bin/env bash
# Gets a laptop ready to run Navuuna with no internet (work pack K-20, PRD R10). Run it once
# while online: it stores the frozen database dump, downloads and builds every image, and
# builds the web app with its basemap. After that, infra/offline/start.sh needs no network.
# Usage: infra/offline/prepare.sh path/to/navuuna-YYYYMMDD-HHMMSS.dump
#        (the .counts file that backup.sh made must sit next to the .dump)

set -euo pipefail

# shellcheck source=infra/offline/settings.sh
. "$(dirname "$0")/settings.sh"

# Checks the dump and its row counts exist, then copies both into infra/offline/data/.
store_frozen_dump() {
    local dump_file="$1"
    local counts_file="${dump_file%.dump}.counts"
    if [ ! -f "$dump_file" ] || [ ! -f "$counts_file" ]; then
        echo "Need both $dump_file and $counts_file (backup.sh makes the pair)" >&2
        exit 1
    fi
    mkdir -p "$FROZEN_DATA_FOLDER"
    cp "$dump_file" "$FROZEN_DUMP"
    cp "$counts_file" "$FROZEN_COUNTS"
    echo "Stored $(basename "$dump_file") as the frozen dump"
}

# Pulls the ready-made images and builds ours, so start.sh never has to download one.
download_images() {
    compose pull postgis redis web-offline
    compose build api
}

# Runs a command in apps/web inside a Linux Node container, so every laptop builds the same way.
# The anonymous volumes keep the container's Linux node_modules and tools (the pmtiles binary)
# apart from the laptop's own copies, like the dev `web` service does.
run_in_web_container() {
    docker run --rm --volume "$WEB_FOLDER:/app" --volume /app/node_modules \
        --volume /app/tools --workdir /app --env VITE_DATA_SOURCE=api node:22 sh -c "$1"
}

# The basemap download is Austine's script (apps/web/scripts). It is skipped when the file is
# already there, because the whole Nairobi extract takes a while; delete the file to refresh it.
build_basemap() {
    if [ -f "$BASEMAP_FILE" ]; then
        echo "Keeping existing basemap $BASEMAP_FILE"
        return
    fi
    run_in_web_container "npm run basemap"
}

# Builds the web app, basemap included, into apps/web/dist.
build_web_app() {
    run_in_web_container "npm ci && npm run build"
}

if [ "$#" -ne 1 ]; then
    echo "Usage: infra/offline/prepare.sh path/to/navuuna-YYYYMMDD-HHMMSS.dump" >&2
    exit 1
fi
if [ ! -f "$ENV_FILE" ]; then
    echo "No $ENV_FILE yet: run infra/local/setup.sh once first" >&2
    exit 1
fi

store_frozen_dump "$1"
download_images
build_basemap
build_web_app
echo "Ready. Start it with infra/offline/start.sh (no internet needed)."
