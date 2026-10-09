# infra

Server configuration for Box A (app) and Box B (worker), Bible §8.2, and the local stack that
runs the same pieces on one laptop (K-08). Owned by Khillon (Core).

| Path | What it is |
|---|---|
| `docker-compose.yml` | The local stack: PostGIS, Redis, MinIO, the Laravel API, rollup consumer, scheduler, signal service, Prefect and web app (K-08, K-12). |
| `backup/` | `backup.sh` (database dump → MinIO, copy to Box B) and `restore.sh` (restore into a scratch database and check it) (K-16). |
| `restore-log.md` | Every real restore and its result (K-16). |
| `docker/` | The images the local stack builds: `api.Dockerfile` (Laravel), `signals.Dockerfile` (Python) and `flows.Dockerfile` (Prefect server + worker, started by `prefect-worker-start.sh`). |
| `local/` | `setup.sh` (first start) and `.env.example` (settings every local service reads). |
| `supervisor/rollup-consumer.conf` | Keeps `php artisan engine:consume-batches` running on Box A (K-10). |

## Running locally in under 15 minutes

You need Docker Desktop (or Docker Engine with Compose v2) and about 5 GB of disk. Then:

    git clone git@github.com:Navuuna-W/navuuna.git && cd navuuna
    infra/local/setup.sh

On a laptop with no images cached this took about 9 minutes, most of it downloads. The script
creates `infra/local/.env` with its own `APP_KEY`, builds the images, runs the migrations,
loads the seed data and starts everything:

| Service | Address | What it does |
|---|---|---|
| `api` | http://localhost:8000 (`/up` is the health check) | Laravel API |
| `web` | http://localhost:5173 | Austine's web app, talking to `api` |
| `rollup-consumer` | — | Turns new sub-variable scores into variable scores |
| `scheduler` | — | Runs `engine:sweep` every 10 minutes |
| `signals` | — | Rescores entities when a recompute request arrives |
| `prefect-server` | http://localhost:4200 | Prefect UI: flow runs, schedules, failures (K-12) |
| `prefect-worker` | — | Runs the flows in `services/flows/` (score_all nightly at 02:00) |
| `postgis` | localhost:15432, user/password/database `navuuna` | Database |
| `redis` | localhost:16379 | Streams between the services |
| `minio` | http://localhost:9001, user `navuuna`, password `navuuna-local` | File storage (Chainguard's build of MinIO) |

**Seed data:** Devyan's 500-entity fixture (`data/fixtures/*.sql`) once it lands; until then
the stub `tests/fixtures/local-seed.sql` (one ward, three water points).

**Every day after that:**

    docker compose -f infra/docker-compose.yml up -d --build   # start, picking up code changes
    docker compose -f infra/docker-compose.yml exec api php artisan engine:rollup --all
    docker compose -f infra/docker-compose.yml logs -f signals  # follow one service
    docker compose -f infra/docker-compose.yml exec prefect-worker prefect deployment run 'score_all/score-all' --watch
    docker compose -f infra/docker-compose.yml down             # stop (add -v to wipe the data)

**Switching a module off:** set `MODULES_ENABLED` in `infra/local/.env` (see the comment
there), then `up -d`. Every service reads the same file (ADR-012 §3).

**Not in the stack yet:** Reverb (arrives with Austine's broadcasting work), the FastAPI
`/health` endpoint (K-07). Until Devyan's `services/signals/modules/` lands, `signals` and
`prefect-worker` run the engine's fake test modules, which score no water entity.

## Reading the logs

Every log line, from Laravel and from the Python signal service, is one JSON object with the
same fields: `datetime`, `level_name`, `channel`, `message` and `context` (plus `exception`
from Python). `jq` filters both the same way (work pack K-15, NFR-10).

| Where | Where the logs are | One view of the box |
|---|---|---|
| Box A (Laravel) | `apps/api/storage/logs/navuuna.json-<date>.log`, with `LOG_STACK=json`; one file a day, deleted after 14 days | `tail -f storage/logs/navuuna.json-*.log \| jq` |
| Box B (Python) | stderr of each container, kept by Docker; Box B's compose file (K-07) sets `max-size`/`max-file` so it rotates | `docker compose logs -f --no-log-prefix \| jq -R 'fromjson? // .'` |
| Local stack | stderr of each container | `docker compose -f infra/docker-compose.yml logs -f --no-log-prefix api signals \| jq -R 'fromjson? // .'` |

Only errors: `... | jq 'select(.level_name == "ERROR")'`. One entity:
`... | jq 'select(.context.entity_id == "<id>")'`. `fromjson? // .` keeps the few lines that
are not JSON, such as the `php artisan serve` banner.

## Server files

**How to use:** `supervisor/` files are copied onto the servers during setup (K-04, K-07); nothing here
runs in CI. Paths in them assume the repo is checked out at `/srv/navuuna`.

**How to test:** start the worker by hand against local Redis and PostGIS:
`cd apps/api && php artisan engine:consume-batches --once`.
