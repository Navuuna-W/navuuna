<?php

// Creates the three runtime database roles and gives them read access to the domain schemas.
// Write access is never given here: the migration that creates a table grants it to that
// table's one writer, next to the CREATE TABLE (ADR-004a §2).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One role per writing service (ADR-004a §1): Python ingest, Python signals, Laravel.
     *
     * @var list<string>
     */
    private const RUNTIME_ROLES = ['nv_ingest', 'nv_signals', 'nv_app'];

    /** @var string The seven domain schemas, as one SQL list. */
    private const DOMAIN_SCHEMA_LIST = 'core, raw, records, scores, flags, lens, audit';

    /** @var string The three runtime roles, as one SQL list. */
    private const RUNTIME_ROLE_LIST = 'nv_ingest, nv_signals, nv_app';

    /** PostgreSQL error code for "role still has privileges somewhere else". */
    private const DEPENDENT_OBJECTS_STILL_EXIST = '2BP01';

    /**
     * Create the roles, then let all three read every domain schema.
     *
     * Implements ADR-004a §2 — database roles enforce one writer per table.
     */
    public function up(): void
    {
        foreach (self::RUNTIME_ROLES as $roleName) {
            $this->createRoleIfMissing($roleName);
        }

        DB::statement('GRANT USAGE ON SCHEMA '.self::DOMAIN_SCHEMA_LIST.' TO '.self::RUNTIME_ROLE_LIST);

        // Every table created later in these schemas is readable by all three roles at once.
        DB::statement(
            'ALTER DEFAULT PRIVILEGES IN SCHEMA '.self::DOMAIN_SCHEMA_LIST
            .' GRANT SELECT ON TABLES TO '.self::RUNTIME_ROLE_LIST
        );

        // Only Laravel reads the framework tables in `public` (users, sessions, jobs).
        DB::statement('GRANT USAGE ON SCHEMA public TO nv_app');
    }

    /**
     * Take back the read access, then drop the roles.
     */
    public function down(): void
    {
        DB::statement('REVOKE USAGE ON SCHEMA public FROM nv_app');

        DB::statement(
            'ALTER DEFAULT PRIVILEGES IN SCHEMA '.self::DOMAIN_SCHEMA_LIST
            .' REVOKE SELECT ON TABLES FROM '.self::RUNTIME_ROLE_LIST
        );

        DB::statement('REVOKE USAGE ON SCHEMA '.self::DOMAIN_SCHEMA_LIST.' FROM '.self::RUNTIME_ROLE_LIST);

        foreach (self::RUNTIME_ROLES as $roleName) {
            $this->dropRoleUnlessUsedByAnotherDatabase($roleName);
        }
    }

    /**
     * Create a login-less role, unless it already exists.
     *
     * Roles belong to the whole PostgreSQL server, not to one database, so a second database
     * on the same server (say, a local test database) finds them already there.
     * NOLOGIN: the deploy gives each service its own login and password (ADR-012 §4).
     */
    private function createRoleIfMissing(string $roleName): void
    {
        $isRolePresent = DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$roleName]) !== null;
        if ($isRolePresent) {
            return;
        }

        DB::statement("CREATE ROLE {$roleName} NOLOGIN");
    }

    /**
     * Drop a role, but leave it if another database on the same server still grants it access.
     *
     * Without this, rolling back a local test database would fail while the dev database
     * still uses the same roles. The inner DB::transaction is a savepoint: a failed DROP ROLE
     * rolls back only itself, not the REVOKEs above it in this migration's transaction.
     */
    private function dropRoleUnlessUsedByAnotherDatabase(string $roleName): void
    {
        try {
            DB::transaction(fn () => DB::statement("DROP ROLE IF EXISTS {$roleName}"));
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::DEPENDENT_OBJECTS_STILL_EXIST) {
                throw $exception;
            }
        }
    }
};
