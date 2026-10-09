# infra/backup

Database backups and the check that they can be restored (work pack K-16, NFR-04).

| File | What it does |
|---|---|
| `backup.sh` | Dumps the whole database (`pg_dump --format=custom`) with a row count per table, stores both in MinIO, copies them to a second MinIO if one is set, and deletes backups older than `BACKUP_KEEP_DAYS`. |
| `restore.sh` | Loads a backup into the scratch database `navuuna_restore_check`, checks every table has the row count saved with the backup, adds a row to `infra/restore-log.md`, then drops the scratch database. Never touches the live database. |
| `settings.sh` | Settings and helpers both scripts share. Sourced, not run. |
| `count-rows.sql` | The row count of every table, used by both scripts. |

## How to run it (local stack)

    docker compose -f infra/docker-compose.yml up -d --wait postgis minio
    infra/backup/backup.sh                 # → navuuna-backups/navuuna-<UTC time>.dump + .counts
    infra/backup/restore.sh                # newest backup → scratch database → check → log row
    infra/backup/restore.sh copy           # the same, from the second MinIO (Box B)
    infra/backup/restore.sh backups navuuna-20261009-173212   # one named backup

Backups are in the MinIO console at http://localhost:9001, bucket `navuuna-backups`. The
MinIO client runs from the compose service `backup` (profile `tools`), one container per command.

## Settings

Read from `infra/local/.env`; every one has a default (see `infra/local/.env.example`).

| Setting | Default | Meaning |
|---|---|---|
| `BACKUP_BUCKET` | `navuuna-backups` | Bucket in the MinIO next to the database |
| `BACKUP_MINIO_ADDRESS` | `minio:9000` | That MinIO, as seen from the compose network; user and password are `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` |
| `BACKUP_COPY_MINIO` | — (no copy) | The second MinIO, with user and password: `http://user:password@box-b:9000` |
| `BACKUP_COPY_BUCKET` | — (no copy) | Bucket on the second MinIO |
| `BACKUP_KEEP_DAYS` | `14` | Backups older than this are deleted from both MinIOs; must be 1 or more |

## On the servers (K-04, K-07)

A backup on the database's own disk is not a backup, so Box A's backups are copied to Box B's
MinIO (`BACKUP_COPY_MINIO`, `BACKUP_COPY_BUCKET`). Run it nightly from Box A's crontab:

    0 2 * * * /srv/navuuna/infra/backup/backup.sh >> /var/log/navuuna-backup.log 2>&1

The scripts reach PostgreSQL and MinIO through the compose services `postgis` and `minio`;
point `COMPOSE_FILE` at the servers' compose file if it is not `infra/docker-compose.yml`.
Until the servers exist, the copy to Box B has only been tested against a second bucket on
the local MinIO.

## How to test it

Run `backup.sh` then `restore.sh`: the restore fails (exit 1, no log row) if the dump cannot
be read or any table's row count differs. The counts are taken just before the dump, so on a
busy database a table written to in between can differ by a few rows; run the backup again
when nothing is writing (or restore a named older backup) before treating it as broken. Lint with
`docker run --rm -v "$PWD:/mnt" -w /mnt koalaman/shellcheck:stable -x infra/backup/*.sh`.
After a real restore, commit the new row in `infra/restore-log.md`.
