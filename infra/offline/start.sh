#!/usr/bin/env bash
# Starts Navuuna on a laptop with no internet (work pack K-20, PRD R10): the database, the API
# and the built web app. The first start loads the frozen dump and checks every table's row
# count against it. Run infra/offline/prepare.sh once, while online, before this.
# Stop it with: docker compose -p navuuna-offline -f infra/docker-compose.yml down

set -euo pipefail

# shellcheck source=infra/offline/settings.sh
. "$(dirname "$0")/settings.sh"

# Stops early, with the fix, when prepare.sh has not been run.
check_prepared() {
    local required_file
    for required_file in "$FROZEN_DUMP" "$FROZEN_COUNTS" "$WEB_BUILD_INDEX" "$BASEMAP_FILE"; do
        if [ ! -f "$required_file" ]; then
            echo "Missing $required_file: run infra/offline/prepare.sh first (online)" >&2
            exit 1
        fi
    done
}

# The core schema only exists once the frozen dump is loaded.
is_database_loaded() {
    local core_schema_count
    core_schema_count="$(echo "SELECT count(*) FROM pg_namespace WHERE nspname = 'core';" \
        | run_psql "$DATABASE_NAME" --no-align --tuples-only)"
    [ "$core_schema_count" -eq 1 ]
}

# Rebuilds the database from template0: template1 in the postgis image already has PostGIS,
# which the dump would then try to create a second time (same reason as restore.sh).
# --no-owner --no-acl: the writer roles (ADR-004a) are not in a fresh database, and the local
# API signs in as `navuuna` anyway.
load_frozen_dump() {
    echo "Loading the frozen dump (first start only)"
    empty_the_database
    compose exec -T postgis pg_restore --username=navuuna --dbname="$DATABASE_NAME" \
        --no-owner --no-acl --exit-on-error < "$FROZEN_DUMP"
}

empty_the_database() {
    echo "DROP DATABASE $DATABASE_NAME;" | run_psql postgres
    echo "CREATE DATABASE $DATABASE_NAME TEMPLATE template0;" | run_psql postgres
}

# Fails when any table is missing, extra or has a different number of rows than the dump.
check_row_counts() {
    local restored_counts
    restored_counts="$(mktemp)"
    run_psql "$DATABASE_NAME" --no-align --tuples-only < "$COUNT_ROWS_SQL" > "$restored_counts"
    if ! diff "$FROZEN_COUNTS" "$restored_counts"; then
        echo "Row counts differ from the frozen dump (< dump, > loaded)." >&2
        echo "Check the dump, then run start.sh again: it will load the dump again." >&2
        # Without this, the next start would see the core schema and skip the check.
        empty_the_database
        exit 1
    fi
    echo "All $(wc -l < "$restored_counts" | tr -d ' ') tables match the frozen dump"
    rm -f "$restored_counts"
}

check_prepared
# --pull never: fail at once instead of trying the network for a missing image.
compose up -d --pull never --wait postgis redis
if ! is_database_loaded; then
    load_frozen_dump
    check_row_counts
fi
compose up -d --pull never api web-offline
echo "Running offline: $OFFLINE_WEB_URL"
