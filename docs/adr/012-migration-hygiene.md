# ADR-012 — Migration hygiene: search_path, UUIDv7 default, module flags, provisioning

- **Number:** 012
- **Date:** 7 Oct 2026
- **Status:** Proposed (accepted on merge)
- **Decider:** Khillon (Core — owns migrations, DEC-06)
- **Refs:** DEC-06, K-06, Bible §10, §14.6, §14.12, ADR-004a

## Context

K-06 creates the database. Four details are cheap to get right in the first migrations and
costly to fix after forty more. Bible §14.12 and ADR-004a settle most of the layout, but leave
these open (DEC-06).

## Decision

### 1. `search_path` includes `raw`

Bible §14.12 sets `search_path` to six domain schemas and `public`, leaving out `raw`. The
Laravel `pgsql` connection uses all seven:

```
core,raw,records,scores,flags,lens,audit,public
```

`search_path` is only a fallback for raw SQL. Every model and every migration still names
its schema in full (`core.entities`, `public.users`). Framework tables are named
`public.<table>` in their migrations and in `config/` (migrations log, sessions, cache, jobs),
so they land in `public` (ADR-004a §1) whatever the path order.

### 2. UUIDv7 IDs come from the database

PostgreSQL 16 has no built-in UUIDv7. Migration `0000_00_00_000001` adds
`public.uuid_generate_v7()` (RFC 9562 §5.7), and every `id` column uses it as its default.
Python services can insert rows without making IDs, and IDs from any writer sort by creation
time.

Laravel models that insert rows also use `HasUuids`, which makes a UUIDv7 in PHP so Eloquent
knows the ID before the insert. Both give the same kind of ID.

### 3. Module flags come from the environment

`MODULES_ENABLED` (for example `water,roads`) is read by `config/modules.php` and by the
Python runner, so a module can be switched off without a deploy (Bible §14.6). Migrations
always create every module's tables: a flag turns code off, never schema. Built in K-08.

### 4. What the server provides before `migrate` runs

On staging and production, `php artisan migrate` runs as `nv_owner` (ADR-004a §2), which is
not a superuser. The provisioning step (K-04) therefore does these first, as a superuser:

- `CREATE EXTENSION postgis` in the application database. Only a superuser may create it.
- Create the runtime roles `nv_ingest`, `nv_signals` and `nv_app`, and give each service a
  login role with its own password that is a member of one of them. Passwords stay in the
  deploy secrets.

The migrations still say `CREATE EXTENSION IF NOT EXISTS` and create a role only if it is
missing, so the same migrations build a full database in CI and locally, where the
connecting user is a superuser, and change nothing on a provisioned server.

Rolling back (`down()`) drops the domain schemas and the roles, but leaves PostGIS, because
only a superuser can drop it. A role that another database on the same server still uses is
left in place.

## Consequences

- K-06a1a ships §1 (search_path), K-06a1b ships §2 and the role and PostGIS handling in §4.
  K-08 ships §3. K-04 does the provisioning in §4.
- Every new table uses `public.uuid_generate_v7()` as its `id` default.
- The `postgis/postgis` image also ships `tiger`, `tiger_data` and `topology` schemas. They
  come from extensions in the image and are not ours, so `\dn` shows them next to the seven.
