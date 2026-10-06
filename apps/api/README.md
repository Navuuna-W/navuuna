# apps/api — Navuuna Laravel API

Laravel 13 on PHP 8.3 (ADR-009). It owns **every database migration**, including tables the
Python services write (ADR-004a). Later it will also serve the API, the tiles and the rollup
engine.

## What lives here

| Path | What |
|---|---|
| `database/migrations/0000_*` | Database foundations: the seven domain schemas, `public.uuid_generate_v7()`, the runtime roles `nv_ingest` / `nv_signals` / `nv_app` |
| `database/migrations/0001_*` | Laravel framework tables (users, sessions, cache, jobs), all in `public` |
| `app/Models/` | Eloquent models. Every model names its schema: `protected $table = 'core.entities';` |
| `tests/Feature/Database/` | Pest tests that run the migrations against real PostGIS |

## Database rules (read before writing a migration)

1. **Name the schema** on every table: `Schema::create('core.entities', …)`. The
   `migration-lint` CI job fails any table without one.
2. **Write a real `down()`**. `MigrationRollbackTest` rolls everything back and fails if a
   table, schema or function is left behind.
3. **Grant the table to its one writer** in the same migration, right after creating it, as the
   ADR-004a appendix says. Every runtime role can already read every domain table.
4. **IDs default to `public.uuid_generate_v7()`** (ADR-012 §2).

## Run it locally

You need PHP 8.3+, Composer and Docker. Until the compose stack lands (K-08), start a
throwaway PostGIS:

```sh
docker run -d --name navuuna-pg -p 55432:5432 \
  -e POSTGRES_DB=navuuna_test -e POSTGRES_USER=navuuna -e POSTGRES_PASSWORD=navuuna \
  postgis/postgis:16-3.4

cd apps/api
composer install
export DB_PORT=55432          # phpunit.xml defaults to 5432, the CI port
php artisan migrate:fresh     # needs a .env: cp .env.example .env, then php artisan key:generate
```

## Test it

```sh
cd apps/api
./vendor/bin/pest                       # tests, against the PostGIS above
./vendor/bin/phpstan analyse            # level 6, Larastan
./vendor/bin/pint --test                # style, with declare(strict_types=1) enforced
```

CI runs the same three commands in the `php` job, against a `postgis/postgis:16-3.4` service.
