<?php

// Creates records.review_queue: aligned rows the aligner was unsure about (confidence below 0.6),
// held for a person to approve or reject with Devyan's review CLI. Only Python ingest (`nv_ingest`)
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
     * Implements Bible §10 (records.review_queue), Bible §7.2 (confidence < 0.6 goes to review)
     * and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('records.review_queue', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // Which records table the candidate goes into once approved.
            $table->text('target_table');
            // The aligned row, with the block id behind each field.
            $table->jsonb('candidate');
            $table->double('alignment_confidence');
            $table->text('reason');
            // The reviewer's name from the CLI; review is offline, not a signed-in user.
            $table->text('reviewed_by')->nullable();
            $table->text('decision')->nullable();
        });

        DB::statement(
            "ALTER TABLE records.review_queue ADD CONSTRAINT review_queue_target_table_check
             CHECK (target_table IN ('records.water_schemes', 'records.road_contracts'))"
        );
        DB::statement(
            'ALTER TABLE records.review_queue ADD CONSTRAINT review_queue_alignment_confidence_check
             CHECK (alignment_confidence BETWEEN 0 AND 1)'
        );
        // A decision always names who made it, and a reviewer is only recorded with a decision.
        DB::statement(
            "ALTER TABLE records.review_queue ADD CONSTRAINT review_queue_decision_check
             CHECK (
                 (reviewed_by IS NULL AND decision IS NULL)
                 OR (reviewed_by IS NOT NULL AND decision IN ('approved', 'rejected'))
             )"
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON records.review_queue TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('records.review_queue');
    }
};
