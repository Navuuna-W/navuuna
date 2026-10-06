<?php

// Creates scores.sub_variable_scores: one row per adapter answer for one entity and one
// sub-variable. The Python runner appends rows; nothing is ever updated, so the history of every
// score stays intact. The rollup reads the newest row per entity × sub-variable.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table, its CHECKs, its hot-path index and its one writer's GRANT.
     *
     * Implements Bible §6.1 and §10 (scores.sub_variable_scores), the same rules as the Python
     * ScoreResult (services/signals/engine/score_result.py), and ADR-004a (nv_signals, INSERT only).
     */
    public function up(): void
    {
        Schema::create('scores.sub_variable_scores', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('entity_id')->constrained('core.entities');
            $table->text('sub_id');
            // jsonb because an adapter's value is a number or a text label (ScoreResult: float | str).
            $table->jsonb('value')->nullable();
            $table->text('unit')->nullable();
            $table->double('score')->nullable();
            $table->double('confidence')->nullable();
            $table->text('status');
            $table->text('null_reason')->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->timestampTz('computed_at')->useCurrent();
            $table->foreignUuid('adapter_id')->constrained('core.adapters');
            $table->text('adapter_version');
        });

        // Blueprint has no array column type. No foreign key is possible on an array: the ids
        // point at rows in several tables (records, eo_stats, observations).
        DB::statement("ALTER TABLE scores.sub_variable_scores ADD COLUMN source_ids uuid[] NOT NULL DEFAULT '{}'");

        // The rollup asks for "the newest score per entity × sub-variable"; newest first makes that one index read.
        DB::statement(
            'CREATE INDEX sub_variable_scores_entity_sub_computed_index
             ON scores.sub_variable_scores (entity_id, sub_id, computed_at DESC)'
        );

        $this->addValueRangeChecks();
        $this->addFieldsMatchStatusCheck();

        // Append-only: INSERT, never UPDATE, so history cannot be rewritten (ADR-004a §2).
        DB::statement('GRANT INSERT ON scores.sub_variable_scores TO nv_signals');
    }

    /**
     * Drop the table. Its GRANT, CHECKs and index go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores.sub_variable_scores');
    }

    /**
     * Sub-variable IDs look like "2.1" (Bible §6.8); the list itself lives in the signal service's
     * code. Scores are 0–100, confidence 0–1 (Bible §6.1).
     */
    private function addValueRangeChecks(): void
    {
        DB::statement(
            "ALTER TABLE scores.sub_variable_scores ADD CONSTRAINT sub_variable_scores_sub_id_format_check
             CHECK (sub_id ~ '^[1-5]\\.[0-9]+$')"
        );
        DB::statement(
            'ALTER TABLE scores.sub_variable_scores ADD CONSTRAINT sub_variable_scores_ranges_check
             CHECK (score BETWEEN 0 AND 100 AND confidence BETWEEN 0 AND 1)'
        );
    }

    /**
     * A measured row carries all its evidence; an unmeasured row carries a reason and no number.
     * Never 0 for missing data — Bible §6.1, CLAUDE.md §4.
     */
    private function addFieldsMatchStatusCheck(): void
    {
        DB::statement(
            "ALTER TABLE scores.sub_variable_scores ADD CONSTRAINT sub_variable_scores_fields_match_status_check
             CHECK (
                 (status = 'measured'
                     AND value IS NOT NULL AND score IS NOT NULL AND confidence IS NOT NULL
                     AND observed_at IS NOT NULL AND cardinality(source_ids) > 0
                     AND null_reason IS NULL)
                 OR (status = 'null_not_measured'
                     AND value IS NULL AND score IS NULL AND confidence IS NULL
                     AND null_reason IS NOT NULL AND btrim(null_reason) <> '')
             )"
        );
    }
};
