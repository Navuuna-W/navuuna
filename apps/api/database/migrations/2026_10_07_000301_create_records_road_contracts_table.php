<?php

// Creates records.road_contracts: what the aligner read from road contract reports, one row per
// contract report, each pointing at the text block it came from. Only Python ingest (`nv_ingest`)
// writes here.

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
     * Implements Bible §10 (records.road_contracts), Bible §7.2 (every field traceable to a block)
     * and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('records.road_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // Null until the contract is matched to a segment entity.
            $table->foreignUuid('entity_id')->nullable()->index()->constrained('core.entities');
            // Every declared value is null when the report does not state it, never 0 (Bible §6.1).
            $table->text('contractor')->nullable();
            $table->double('length_km_declared')->nullable();
            $table->decimal('value_kes', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->double('pct_complete_declared')->nullable();
            $table->date('report_date')->nullable();
            $table->foreignUuid('document_id')->constrained('raw.documents');
            $table->foreignUuid('block_id')->constrained('raw.text_blocks');
            $table->text('aligner_version');
            $table->double('alignment_confidence');
        });

        DB::statement(
            'ALTER TABLE records.road_contracts ADD CONSTRAINT road_contracts_alignment_confidence_check
             CHECK (alignment_confidence BETWEEN 0 AND 1)'
        );
        DB::statement(
            'ALTER TABLE records.road_contracts ADD CONSTRAINT road_contracts_declared_values_check
             CHECK (
                 pct_complete_declared BETWEEN 0 AND 100
                 AND length_km_declared >= 0
                 AND value_kes >= 0
                 AND end_date >= start_date
             )'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON records.road_contracts TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('records.road_contracts');
    }
};
