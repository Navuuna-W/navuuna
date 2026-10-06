<?php

// Creates core.adapters: one row per adapter version the signal service has registered.
// The Python registry (K-09b) syncs this table from the code, so only `nv_signals` writes here.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table, its CHECK and its one writer's GRANT.
     *
     * Implements Bible §10 (core.adapters) and ADR-004a appendix (writer: nv_signals).
     */
    public function up(): void
    {
        Schema::create('core.adapters', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->text('module');
            $table->text('sub_id');
            $table->text('version');
            $table->boolean('enabled')->default(true);
            $table->text('signal_description');
            $table->text('code_ref');

            // Sub-variable IDs are unique across modules (Bible §6.8), so sub_id + version is enough.
            $table->unique(['sub_id', 'version']);
        });

        // Blueprint has no array column type, so this one is added in SQL.
        DB::statement('ALTER TABLE core.adapters ADD COLUMN entity_types text[] NOT NULL');

        // At least one type, and only the four from Bible §5.
        DB::statement(
            "ALTER TABLE core.adapters ADD CONSTRAINT adapters_entity_types_check
             CHECK (
                 cardinality(entity_types) > 0
                 AND entity_types <@ ARRAY['point', 'parcel', 'segment', 'area']::text[]
             )"
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON core.adapters TO nv_signals');
    }

    /**
     * Drop the table. Its GRANT and CHECK go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('core.adapters');
    }
};
