<?php

// Creates raw.eo_stats: satellite index values (NDWI, NDVI, NDBI) summarised over one entity's
// footprint on one acquisition date. Adapters read them as inputs. Only Python ingest
// (`nv_ingest`) writes here.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table, its CHECK, its lookup index and its one writer's GRANT.
     *
     * Implements Bible §10 (raw.eo_stats) and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('raw.eo_stats', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('source_id')->constrained('core.sources');
            $table->foreignUuid('entity_id')->constrained('core.entities');
            $table->text('product');
            $table->text('index_name');
            // Required: a reading lost to cloud is no row at all, never a 0 (Bible §6.1, A6).
            $table->double('value');
            $table->timestampTz('acquired_at');
            $table->double('cloud_pct')->nullable();
        });

        // Adapters ask for "the newest NDWI for this entity"; newest first makes that one index read.
        DB::statement(
            'CREATE INDEX eo_stats_entity_index_acquired_index
             ON raw.eo_stats (entity_id, index_name, acquired_at DESC)'
        );

        DB::statement(
            'ALTER TABLE raw.eo_stats ADD CONSTRAINT eo_stats_cloud_pct_check
             CHECK (cloud_pct BETWEEN 0 AND 100)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON raw.eo_stats TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT, CHECK and index go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw.eo_stats');
    }
};
