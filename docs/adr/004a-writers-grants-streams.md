# ADR-004a — One writer per table, enforced by database roles; Redis stream contracts; reconciliation sweep

- **Number:** 004a (amends ADR-004, Bible §10.1)
- **Date:** 29 Sep 2026
- **Status:** Proposed — becomes Accepted when Devyan signs (approves this PR)
- **Decider:** Khillon (Core — owns migrations, the signal runner and the rollup engine)
- **Signs:** Devyan (Signal — owns the Python ingest that writes most of `raw`, `records` and `core`)

## Context

ADR-004 (Bible §10.1) set the rule **one writer per table** and split the writers by schema:
Python writes `raw.*` and `scores.sub_variable_scores`; Laravel writes everything else,
including `core.*` and `records.*`.

The rule is right. The split is wrong for three groups of tables, because the code that fills them is Python:

- The aligner and review CLI (Bible §7.2) are Python. They write `records.*`.
- The base-layer loaders (FR-01) are Python. They write `core.entities` and `core.sources`.
- The adapter registry (Bible §4.4) is Python. It writes `core.adapters`.

The rule is also only a convention today: every service would connect as the same database
user, so nothing stops a wrong write. And ADR-004 says Python tells Laravel about new scores
with "a Redis event" but does not say what the event contains, who reads it, or what happens
if it is lost.

## Decision

### 1. Who writes which table

Each table has exactly one writing service. The full list is in the **Appendix**. In short:

| Service | Database role | Writes |
|---|---|---|
| Python ingest (Devyan) | `nv_ingest` | `raw.*`, `records.*`, `core.entities`, `core.sources` |
| Python signals — registry + runner (Khillon) | `nv_signals` | `core.adapters`, `scores.sub_variable_scores` |
| Laravel (Khillon, Austine) | `nv_app` | everything else, including the Laravel framework tables |

**Laravel framework tables** (users, API keys, sessions, jobs, failed jobs, cache, migrations
log) live in the **`public`** schema, next to PostGIS. The Bible fixes seven domain schemas
(§10); a new `app` schema would make eight, and putting framework tables in `core` would mix
them with scored data.

### 2. Database roles enforce it

| Role | Used by | Can do |
|---|---|---|
| `nv_owner` | `php artisan migrate` during deploy only | Owns every schema and table. The only role that can CREATE, ALTER or DROP. |
| `nv_ingest` | Python ingest (Box B) | USAGE + SELECT on the seven domain schemas; writes as in the Appendix |
| `nv_signals` | Python signal service (Box B) | USAGE + SELECT on the seven domain schemas; writes as in the Appendix |
| `nv_app` | Laravel web, queue workers, scheduler, Reverb (Box A) | USAGE + SELECT on the seven domain schemas and `public`; writes as in the Appendix |

Rules for the GRANTs:

- **Writers get INSERT and UPDATE on their own tables only.** Everyone else gets SELECT.
- **Append-only tables get INSERT only**, so history cannot be rewritten:
  `scores.sub_variable_scores`, `scores.variable_scores`, `flags.transitions`, `audit.events`.
- **No role gets DELETE**, except `nv_app` on the framework tables in `public` (sessions,
  cache and jobs expire). Retiring or withdrawing something is an UPDATE of a timestamp
  (`retired_at`, `withdrawn_at`). Any other DELETE needs an amendment to this ADR.
- `nv_ingest` and `nv_signals` get no access to `public` framework tables (users, keys).
- Laravel still owns **all** migrations (ADR-004 unchanged on this point). The migration that
  creates a table also writes its GRANT, next to the `CREATE TABLE`, so the two never drift.
- `ALTER DEFAULT PRIVILEGES` gives SELECT on new domain tables to all three runtime roles.
  Write access is never a default; it is always granted by name.

### 3. Redis stream contracts

Two streams on the Box A Redis. Stream fields are flat strings, so lists and maps are sent as
JSON strings. Timestamps are ISO 8601 UTC.

**`signals.batch_written`** — published by the Python runner after it commits a chunk of
sub-variable scores.

| Field | Type | Example |
|---|---|---|
| `batch_id` | UUIDv7 string | `0192…` |
| `entity_ids` | JSON array of UUID strings, **at most 500** | `["0192…","0192…"]` |
| `module` | string | `water` |
| `adapter_versions` | JSON object, sub_id → version | `{"1.2":"1.0.0"}` |
| `written_at` | timestamp | `2026-09-29T10:15:00Z` |

A runner batch larger than 500 entities is published as several messages with the same
`batch_id`.

**`signals.recompute_requested`** — published by Laravel (a new or withdrawn community
observation, an admin action) when one entity's sub-variable scores must be recomputed.

| Field | Type | Example |
|---|---|---|
| `entity_id` | UUID string | `0192…` |
| `reason` | string: `observation_added`, `observation_withdrawn`, `consent_withdrawn`, `admin` | `observation_withdrawn` |
| `requested_by` | user UUID string, or `system` | `0192…` |
| `requested_at` | timestamp | `2026-09-29T10:15:00Z` |

**Reading the streams:**

| Stream | Consumer group | Reader |
|---|---|---|
| `signals.batch_written` | `rollup` | Laravel rollup worker (Box A) |
| `signals.recompute_requested` | `runner` | Python signal runner (Box B) |

- A reader acknowledges a message (`XACK`) **only after its database work has committed.**
  A crash before commit leaves the message pending.
- A message pending for more than **5 minutes** is taken over by another reader
  (`XAUTOCLAIM`) and processed again.
- Because a message can be processed twice, **rollup and recompute must be idempotent**:
  running them twice for the same entity gives the same scores.
- Each stream is trimmed to about **100 000** messages (`XADD … MAXLEN ~ 100000`).
- Adding a field is allowed. Renaming or removing one needs an amendment to this ADR.

### 4. Reconciliation sweep

A stream message can still be lost (Redis restart, a bug in a reader). So the Laravel
scheduler on Box A runs a **sweep every 10 minutes**:

> Roll up every entity that is not retired and whose newest `scores.sub_variable_scores` row
> is newer than its newest `scores.variable_scores` row, or that has sub-variable scores and
> no variable score at all.

The sweep uses the same idempotent rollup as the stream reader. A lost event can therefore
leave scores stale for at most about 10 minutes, never for good.

## Consequences

- **Bible §10.1 is amended** by the writer list above. Where they differ, this ADR wins.
- **K-06 (migrations)** creates the four roles, puts framework tables in `public` and writes
  the GRANTs next to each `CREATE TABLE`. Its tests check that a wrong role's INSERT fails.
- **K-09b (runner)** publishes `batch_written` and reads `recompute_requested` as above.
- **K-10 (rollup)** reads `batch_written`, acknowledges after commit, and runs the sweep.
- **Austine's observation endpoints** publish `recompute_requested` with the reasons above.
- Each service needs its own database password. They live in the deploy secrets, never in
  the repo.
- A later CI check should fail if any domain table has no writer role or more than one.
- A new table must be added to the Appendix in the same PR that creates it.

## Appendix — writer matrix

| Table | Only writer | Role | Write rights |
|---|---|---|---|
| `raw.documents` | Python ingest — scrapers (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `raw.text_blocks` | Python ingest — parser (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `raw.eo_stats` | Python ingest — EO (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `raw.vectors` | Python ingest — loaders (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `records.water_schemes` | Python ingest — aligner (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `records.road_contracts` | Python ingest — aligner (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `records.review_queue` | Python ingest — aligner + review CLI (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `core.entities` | Python ingest — loaders (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `core.sources` | Python ingest — loaders (Devyan) | `nv_ingest` | INSERT, UPDATE |
| `core.adapters` | Python signals — registry (Khillon) | `nv_signals` | INSERT, UPDATE |
| `scores.sub_variable_scores` | Python signals — runner (Khillon) | `nv_signals` | INSERT only |
| `core.observations` | Laravel — observations (Austine) | `nv_app` | INSERT, UPDATE |
| `core.consents` | Laravel — observations (Austine) | `nv_app` | INSERT, UPDATE |
| `scores.variable_scores` | Laravel — rollup (Khillon) | `nv_app` | INSERT only |
| `flags.flags` | Laravel — findings (Khillon) | `nv_app` | INSERT, UPDATE |
| `flags.evidence_packs` | Laravel — findings (Khillon) | `nv_app` | INSERT, UPDATE |
| `flags.transitions` | Laravel — FlagWorkflow (Khillon) | `nv_app` | INSERT only |
| `flags.outcomes` | Laravel — outcomes command (Khillon) | `nv_app` | INSERT, UPDATE |
| `lens.lenses` | Laravel — lens application (Austine) | `nv_app` | INSERT, UPDATE |
| `lens.lens_scores` | Laravel — lens application (Austine) | `nv_app` | INSERT, UPDATE |
| `audit.events` | Laravel — audit (Khillon) | `nv_app` | INSERT only |
| `public` framework tables: users, API keys, sessions, jobs, failed jobs, cache, migrations log | Laravel — auth + framework (Khillon) | `nv_app` (migrations log: `nv_owner`) | INSERT, UPDATE, DELETE |
