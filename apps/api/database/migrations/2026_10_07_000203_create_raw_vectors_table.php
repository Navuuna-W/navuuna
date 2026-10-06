<?php

// Creates raw.vectors: one row per vector layer a loader brought in (e.g. OSM water points),
// with where the file sits in MinIO. Only Python ingest (`nv_ingest`) writes here.

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
     * Implements Bible §10 (raw.vectors) and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('raw.vectors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('source_id')->constrained('core.sources');
            $table->text('layer');
            $table->integer('feature_count');
            $table->timestampTz('loaded_at')->useCurrent();
            // Path of the layer file in MinIO; the features themselves become core.entities.
            $table->text('storage_path');
        });

        DB::statement(
            'ALTER TABLE raw.vectors ADD CONSTRAINT vectors_feature_count_check
             CHECK (feature_count >= 0)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON raw.vectors TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECK go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw.vectors');
    }
};
