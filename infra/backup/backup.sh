#!/usr/bin/env bash
# Nightly database backup (work pack K-16, NFR-04). Dumps the whole database, stores the dump
# in MinIO, copies it to a second MinIO (Box B on the servers) and deletes old backups.
# Run from anywhere: infra/backup/backup.sh. infra/backup/restore.sh proves a backup works.

set -euo pipefail

# shellcheck source=infra/backup/settings.sh
. "$(dirname "$0")/settings.sh"

# Exact row count of every table, so restore.sh can prove the restored copy is complete.
# Taken just before the dump: on a busy database a few rows may be written in between.
count_rows() {
    local counts_file="$1"
    run_psql "$DATABASE_NAME" --no-align --tuples-only < "$COUNT_ROWS_SQL" > "$counts_file"
}

# pg_dump's custom format is compressed and lets pg_restore rebuild a single table if needed.
dump_database() {
    local dump_file="$1"
    compose exec -T postgis pg_dump --username=navuuna --dbname="$DATABASE_NAME" \
        --format=custom > "$dump_file"
}

# Stores the dump and its row counts in one bucket, making the bucket on the first run.
upload_backup() {
    local minio_alias="$1" bucket="$2"
    run_minio_client mb --ignore-existing "$minio_alias/$bucket"
    run_minio_client cp "/backups/$BACKUP_NAME.dump" "/backups/$BACKUP_NAME.counts" \
        "$minio_alias/$bucket/"
}

delete_old_backups() {
    local minio_alias="$1" bucket="$2"
    run_minio_client rm --recursive --force --older-than "${BACKUP_KEEP_DAYS}d" \
        "$minio_alias/$bucket/"
}

BACKUP_NAME="navuuna-$(date -u +%Y%m%d-%H%M%S)"
create_work_folder

count_rows "$WORK_FOLDER/$BACKUP_NAME.counts"
dump_database "$WORK_FOLDER/$BACKUP_NAME.dump"

upload_backup backups "$BACKUP_BUCKET"
delete_old_backups backups "$BACKUP_BUCKET"

# A backup on the database's own disk is not a backup: the servers also keep a copy on Box B.
if [ -n "$BACKUP_COPY_BUCKET" ]; then
    upload_backup copy "$BACKUP_COPY_BUCKET"
    delete_old_backups copy "$BACKUP_COPY_BUCKET"
fi

echo "Backup $BACKUP_NAME stored ($(du -h "$WORK_FOLDER/$BACKUP_NAME.dump" | awk '{print $1}'))"
