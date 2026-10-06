<?php

// Creates records.water_schemes: what the aligner read from a water scheme register, one row per
// scheme, each pointing at the text block it came from. Adapters compare these declared values with
// what is observed. Only Python ingest (`nv_ingest`) writes here.

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
     * Implements Bible §10 (records.water_schemes), Bible §7.2 (every field traceable to a block)
     * and ADR-004a appendix (writer: nv_ingest). reported_production_m3d is added for adapter 1.3
     * (docs/modules/water.md), named in the Bible's style (decision 7 Oct).
     */
    public function up(): void
    {
        Schema::create('records.water_schemes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // Null until the record is matched to a scored entity.
            $table->foreignUuid('entity_id')->nullable()->index()->constrained('core.entities');
            $table->text('name');
            // Every declared value is null when the register does not state it, never 0 (Bible §6.1).
            $table->text('scheme_type')->nullable();
            $table->double('rated_yield_m3d')->nullable();
            $table->double('reported_production_m3d')->nullable();
            $table->text('status_declared')->nullable();
            $table->text('operator')->nullable();
            $table->date('record_date')->nullable();
            $table->foreignUuid('document_id')->constrained('raw.documents');
            $table->foreignUuid('block_id')->constrained('raw.text_blocks');
            $table->text('aligner_version');
            $table->double('alignment_confidence');
        });

        DB::statement(
            'ALTER TABLE records.water_schemes ADD CONSTRAINT water_schemes_alignment_confidence_check
             CHECK (alignment_confidence BETWEEN 0 AND 1)'
        );
        DB::statement(
            'ALTER TABLE records.water_schemes ADD CONSTRAINT water_schemes_volumes_not_negative_check
             CHECK (rated_yield_m3d >= 0 AND reported_production_m3d >= 0)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON records.water_schemes TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('records.water_schemes');
    }
};
