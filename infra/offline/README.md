# infra/offline

The offline stack: Navuuna on one laptop with no internet, for when the venue network fails
(work pack K-20, PRD R10). It runs the frozen data, the API and the built web app. Owned by
Khillon (Core).

| File | What it is |
|---|---|
| `prepare.sh` | Run once, **online**. Stores the frozen dump, downloads and builds the images, builds the web app and its basemap. |
| `start.sh` | Run **offline**: the one command. Starts the stack; the first start loads the dump and checks every table's row count. |
| `settings.sh` | Paths and the compose project name shared by both scripts. |
| `nginx.conf` | The `web-offline` service: serves `apps/web/dist`, passes `/api`, `/sanctum` and `/tiles` to the API. |
| `data/` | The frozen dump (`frozen.dump` + `frozen.counts`). Not in git: dumps are bigger than 1 MB. |

It is the normal `infra/docker-compose.yml` run as a separate project, `navuuna-offline`,
with the `offline` profile. It has its own containers and database volume, so it never touches
your everyday local data. It uses the same ports, so stop the dev stack first
(`docker compose -f infra/docker-compose.yml down`).

## 1. Get a dump (online)

Any backup from `infra/backup/backup.sh` works: the `.dump` and the `.counts` it makes together.
To freeze your local data, make a backup and copy the pair out of MinIO:

    infra/backup/backup.sh                         # prints: Backup navuuna-YYYYMMDD-HHMMSS stored
    mkdir -p /tmp/frozen && chmod 777 /tmp/frozen
    bash -c '. infra/backup/settings.sh && WORK_FOLDER=/tmp/frozen && run_minio_client cp \
        backups/navuuna-backups/navuuna-YYYYMMDD-HHMMSS.dump \
        backups/navuuna-backups/navuuna-YYYYMMDD-HHMMSS.counts /backups/'

## 2. Prepare the laptop (online, once)

Needs Docker and `infra/local/.env` (run `infra/local/setup.sh` once if it is missing).

    infra/offline/prepare.sh /tmp/frozen/navuuna-YYYYMMDD-HHMMSS.dump

The basemap download is skipped when `apps/web/public/basemap/nairobi.pmtiles` already exists;
delete it to download a fresh one.

## 3. Start it (no internet)

    infra/offline/start.sh            # then open http://localhost:5173

Stop it with `docker compose -p navuuna-offline -f infra/docker-compose.yml down`. Add `-v` to
throw away its database, so the next start loads the frozen dump again.

**Not running offline:** the signal service, the rollup consumer, the scheduler and Prefect.
The data is frozen, so nothing needs rescoring. The writer roles (ADR-004a) are not created
either: the API signs in as `navuuna`, as in the local stack.

## Testing it

There are no unit tests: the scripts are tested by running them. Check these three cases:

- `start.sh` before `prepare.sh` stops with "run infra/offline/prepare.sh first".
- Edit one count in `data/frozen.counts`, then `down -v` and `start.sh`: it stops with the
  difference and empties the database, so the next start loads and checks it again.
- With Wi-Fi off, `start.sh` prints "All N tables match the frozen dump" and the map loads.
