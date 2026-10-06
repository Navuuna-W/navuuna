<?php

// Creates lens.lenses: each customer view of the five variables (e.g. the county planner lens),
// stored as versioned JSON config. A lens weights variables only — never sub-variables.
// Laravel loads lenses (`nv_app`) from the lens JSON files.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table, its CHECKs and its one writer's GRANT.
     *
     * Implements Bible §6.6 and §10 (lens.lenses: config holds variable_weights, thresholds and
     * direction, no sub_weights), CLAUDE.md §4 and ADR-004a appendix (writer: nv_app).
     */
    public function up(): void
    {
        Schema::create('lens.lenses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->text('name');
            $table->text('customer_type');
            $table->jsonb('config');
            $table->integer('version');
            $table->boolean('active')->default(true);

            // A changed lens is a new version, so old lens scores still say which config made them.
            $table->unique(['name', 'version']);
        });

        DB::statement(
            'ALTER TABLE lens.lenses ADD CONSTRAINT lenses_version_check CHECK (version >= 1)'
        );
        // Hard rule: no sub-variable weights in a lens. Sub-variable weights live only in
        // weights.yml and change only by ADR (CLAUDE.md §4, Bible §6.6).
        // `->` returns SQL NULL only when the key is absent. (The jsonb `?` operator would read
        // the same, but PDO mistakes `?` for a query placeholder.)
        DB::statement(
            "ALTER TABLE lens.lenses ADD CONSTRAINT lenses_weights_variables_only_check
             CHECK (config -> 'variable_weights' IS NOT NULL AND config -> 'sub_weights' IS NULL)"
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON lens.lenses TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('lens.lenses');
    }
};
