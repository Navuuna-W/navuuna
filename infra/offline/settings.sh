# Settings shared by prepare.sh and start.sh (work pack K-20). Sourced, not run.
# The offline stack is the normal compose file run as its own project, `navuuna-offline`,
# so it gets its own containers and database volume and never touches the everyday local data.
# shellcheck shell=bash
# shellcheck disable=SC2034  # the variables here are used by the scripts that source it

export COMPOSE_PROJECT_NAME="navuuna-offline"
# Turns on web-offline, the nginx service that serves the built web app.
export COMPOSE_PROFILES="offline"

# Reuses compose(), run_psql(), REPO_ROOT, ENV_FILE and COUNT_ROWS_SQL from the backups (K-16).
# shellcheck source=infra/backup/settings.sh
. "$(dirname "${BASH_SOURCE[0]}")/../backup/settings.sh"

OFFLINE_FOLDER="$REPO_ROOT/infra/offline"
# Holds the frozen dump; .gitignored because dumps are bigger than 1 MB (CLAUDE.md §4).
FROZEN_DATA_FOLDER="$OFFLINE_FOLDER/data"
FROZEN_DUMP="$FROZEN_DATA_FOLDER/frozen.dump"
FROZEN_COUNTS="$FROZEN_DATA_FOLDER/frozen.counts"

WEB_FOLDER="$REPO_ROOT/apps/web"
WEB_BUILD_INDEX="$WEB_FOLDER/dist/index.html"
BASEMAP_FILE="$WEB_FOLDER/public/basemap/nairobi.pmtiles"

# The address nginx serves on. 5173 is the dev web app's port too, so the
# SANCTUM_STATEFUL_DOMAINS in infra/local/.env already accepts it.
OFFLINE_WEB_URL="http://localhost:5173"
