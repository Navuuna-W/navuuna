<?php

// The first migration of all: creates the seven domain schemas and makes sure PostGIS is on.
// Every later migration puts its tables in one of these schemas (Bible §10, §14.12).
// Laravel framework tables stay in `public`, next to PostGIS (ADR-004a §1).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The seven domain schemas, in the order Bible §10 lists them.
     *
     * @var list<string>
     */
    private const DOMAIN_SCHEMAS = ['core', 'raw', 'records', 'scores', 'flags', 'lens', 'audit'];

    /**
     * Create PostGIS (in `public`) and the seven domain schemas.
     *
     * Implements Bible §14.12 — multi-schema setup — and ADR-012 §1.
     */
    public function up(): void
    {
        // Already present on staging and production, where the provisioning superuser creates it
        // (ADR-012 §4). IF NOT EXISTS makes this a no-op there and a real create in CI.
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public');

        foreach (self::DOMAIN_SCHEMAS as $schemaName) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schemaName}");
        }
    }

    /**
     * Drop the seven domain schemas.
     *
     * No CASCADE: every table's own migration drops that table first, so a schema that is still
     * full here means a migration has a broken down(), and we want that to fail loudly.
     * PostGIS is left installed because only a superuser may drop it (ADR-012 §4).
     */
    public function down(): void
    {
        foreach (array_reverse(self::DOMAIN_SCHEMAS) as $schemaName) {
            DB::statement("DROP SCHEMA IF EXISTS {$schemaName}");
        }
    }
};
