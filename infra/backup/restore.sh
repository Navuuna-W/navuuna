#!/usr/bin/env bash
# Proves a backup works (work pack K-16, NFR-04): restores it into a scratch database, checks
# every table has the row count saved with the backup, and adds a row to infra/restore-log.md.
# Never touches the live database. Usage: infra/backup/restore.sh [backups|copy] [backup-name]
# (defaults: the `backups` MinIO, newest backup; `copy` reads Box B's copy instead).

set -euo pipefail

# shellcheck source=infra/backup/settings.sh
. "$(dirname "$0")/settings.sh"

SCRATCH_DATABASE_NAME="navuuna_restore_check"
RESTORE_LOG="$REPO_ROOT/infra/restore-log.md"
MINIO_ALIAS="${1:-backups}"
BACKUP_NAME="${2:-}"

# The bucket to read from: the main one, or Box B's copy when the first argument is `copy`.
bucket_for_alias() {
    if [ "$MINIO_ALIAS" != "copy" ]; then
        echo "$BACKUP_BUCKET"
        return
    fi
    if [ -z "$BACKUP_COPY_BUCKET" ]; then
        echo "No copy is set up: BACKUP_COPY_MINIO and BACKUP_COPY_BUCKET are empty" >&2
        exit 1
    fi
    echo "$BACKUP_COPY_BUCKET"
}

# Backup names hold their UTC time (navuuna-YYYYMMDD-HHMMSS), so the newest sorts last.
find_newest_backup() {
    run_minio_client ls "$MINIO_ALIAS/$BUCKET/" | awk '{print $NF}' | grep '\.dump$' \
        | sort | tail -1 | sed 's/\.dump$//'
}

# Copies the dump and its row counts into $WORK_FOLDER.
download_backup() {
    run_minio_client cp "$MINIO_ALIAS/$BUCKET/$BACKUP_NAME.dump" \
        "$MINIO_ALIAS/$BUCKET/$BACKUP_NAME.counts" /backups/
}

# template0 is empty; template1 in the postgis image already has PostGIS, which the dump
# would then try to create a second time.
create_scratch_database() {
    drop_scratch_database
    echo "CREATE DATABASE $SCRATCH_DATABASE_NAME TEMPLATE template0;" | run_psql postgres
}

# Also runs when the script exits, so a failed check leaves nothing behind.
drop_scratch_database() {
    echo "DROP DATABASE IF EXISTS $SCRATCH_DATABASE_NAME;" | run_psql postgres
}

restore_into_scratch_database() {
    compose exec -T postgis pg_restore --username=navuuna --dbname="$SCRATCH_DATABASE_NAME" \
        --exit-on-error < "$WORK_FOLDER/$BACKUP_NAME.dump"
}

# Fails the restore when any table is missing, extra or has a different number of rows.
check_row_counts() {
    run_psql "$SCRATCH_DATABASE_NAME" --no-align --tuples-only < "$COUNT_ROWS_SQL" \
        > "$WORK_FOLDER/restored.counts"
    if ! diff "$WORK_FOLDER/$BACKUP_NAME.counts" "$WORK_FOLDER/restored.counts"; then
        echo "Restore check FAILED: row counts differ from the backup (< backup, > restored)" >&2
        exit 1
    fi
}

# Adds one table row to infra/restore-log.md, the record K3.5 asks for.
add_restore_log_row() {
    local seconds="$1"
    local table_count row_count dump_size person
    table_count="$(wc -l < "$WORK_FOLDER/restored.counts" | tr -d ' ')"
    row_count="$(awk -F'|' '{total += $2} END {print total}' "$WORK_FOLDER/restored.counts")"
    dump_size="$(du -h "$WORK_FOLDER/$BACKUP_NAME.dump" | awk '{print $1}')"
    person="$(git -C "$REPO_ROOT" config user.name || whoami)"
    echo "| $(date -u '+%Y-%m-%d %H:%M') UTC | $(hostname -s) | $MINIO_ALIAS/$BUCKET/$BACKUP_NAME" \
        "| $dump_size | ${seconds}s | $table_count tables, $row_count rows match | $person |" \
        >> "$RESTORE_LOG"
}

BUCKET="$(bucket_for_alias)"
create_work_folder
# Leave no scratch database behind, whether the check passes or fails.
trap 'drop_scratch_database; rm -rf "$WORK_FOLDER"' EXIT

if [ -z "$BACKUP_NAME" ]; then
    BACKUP_NAME="$(find_newest_backup)"
fi
if [ -z "$BACKUP_NAME" ]; then
    echo "No backup found in $MINIO_ALIAS/$BUCKET — run infra/backup/backup.sh first" >&2
    exit 1
fi

echo "Restoring $MINIO_ALIAS/$BUCKET/$BACKUP_NAME into $SCRATCH_DATABASE_NAME"
download_backup
create_scratch_database
started_at="$(date +%s)"
restore_into_scratch_database
restore_seconds="$(( $(date +%s) - started_at ))"
check_row_counts
add_restore_log_row "$restore_seconds"
echo "Restore check passed in ${restore_seconds}s; row added to infra/restore-log.md"
