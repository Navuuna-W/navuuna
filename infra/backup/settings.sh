# Settings and helpers shared by backup.sh and restore.sh (work pack K-16).
# Sourced, not run. Settings come from infra/local/.env (see .env.example); the defaults
# match the local stack.
# shellcheck shell=bash
# shellcheck disable=SC2034  # the variables here are used by the scripts that source it

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$REPO_ROOT/infra/local/.env"
COMPOSE_FILE="${COMPOSE_FILE:-$REPO_ROOT/infra/docker-compose.yml}"
COUNT_ROWS_SQL="$REPO_ROOT/infra/backup/count-rows.sql"
DATABASE_NAME="navuuna"

# Prints one KEY=value setting from infra/local/.env, or the default when it is not set.
# Only the keys we need are read: the file also holds Laravel values bash cannot source.
read_setting() {
    local key="$1" default_value="$2" value=""
    if [ -f "$ENV_FILE" ]; then
        value="$(grep "^$key=" "$ENV_FILE" | tail -1 | cut -d= -f2-)"
    fi
    echo "${value:-$default_value}"
}

BACKUP_BUCKET="$(read_setting BACKUP_BUCKET navuuna-backups)"
BACKUP_COPY_BUCKET="$(read_setting BACKUP_COPY_BUCKET "")"
BACKUP_KEEP_DAYS="$(read_setting BACKUP_KEEP_DAYS 14)"
# 0 would delete every backup, including the one just made.
if ! [[ "$BACKUP_KEEP_DAYS" =~ ^[1-9][0-9]*$ ]]; then
    echo "BACKUP_KEEP_DAYS must be a whole number of days, 1 or more (got '$BACKUP_KEEP_DAYS')" >&2
    exit 1
fi

# The MinIO client finds each MinIO through an MC_HOST_<alias> variable holding its address
# with the user and password in it. `backups` is the MinIO next to the database; `copy` is
# the second one (Box B), given whole in BACKUP_COPY_MINIO, e.g. http://user:password@host:9000.
MINIO_ADDRESS="$(read_setting BACKUP_MINIO_ADDRESS minio:9000)"
MINIO_USER="$(read_setting MINIO_ROOT_USER navuuna)"
MINIO_PASSWORD="$(read_setting MINIO_ROOT_PASSWORD navuuna-local)"
export MC_HOST_backups="http://$MINIO_USER:$MINIO_PASSWORD@$MINIO_ADDRESS"
MC_HOST_copy="$(read_setting BACKUP_COPY_MINIO "")"
export MC_HOST_copy

compose() {
    docker compose -f "$COMPOSE_FILE" "$@"
}

# Runs psql on a database in the postgis container, reading SQL from stdin.
run_psql() {
    local database_name="$1"
    shift
    compose exec -T postgis psql --username=navuuna --dbname="$database_name" \
        --set=ON_ERROR_STOP=1 --quiet "$@"
}

# Runs the MinIO client with $WORK_FOLDER mounted at /backups. The image has no shell, so
# every mc command is its own short-lived container. `--env NAME` with no value passes the
# exported variable through without putting the password on the command line.
run_minio_client() {
    compose run --rm --no-deps --env MC_HOST_backups --env MC_HOST_copy \
        --volume "$WORK_FOLDER:/backups" backup "$@"
}

# A temporary folder shared with the MinIO client container, deleted when the script exits.
# The client runs as a non-root user, so the folder must be writable by everyone.
create_work_folder() {
    WORK_FOLDER="$(mktemp -d)"
    trap 'rm -rf "$WORK_FOLDER"' EXIT
    chmod 777 "$WORK_FOLDER"
}
