# infra

Server configuration for Box A (app) and Box B (worker), Bible §8.2. Owned by Khillon (Core).

| Path | What it is |
|---|---|
| `supervisor/rollup-consumer.conf` | Keeps `php artisan engine:consume-batches` running on Box A (K-10). |

**How to use:** these files are copied onto the servers during setup (K-04, K-07); nothing here
runs in CI. Paths in them assume the repo is checked out at `/srv/navuuna`.

**How to test:** start the worker by hand against local Redis and PostGIS:
`cd apps/api && php artisan engine:consume-batches --once`.
