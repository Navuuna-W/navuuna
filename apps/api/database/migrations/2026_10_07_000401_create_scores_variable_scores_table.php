<?php

// Creates scores.variable_scores: the rollup's answer for one entity and one of the five
// variables, with its coverage, confidence, status and gate state. Laravel's rollup appends rows;
// nothing is ever updated, so every past score can be explained with the weights that made it.

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
     * Implements Bible §6.2–6.3 and §10 (scores.variable_scores), NFR-08 (weight_version on every
     * row) and ADR-004a (nv_app, INSERT only).
     */
    public function up(): void
    {
        Schema::create('scores.variable_scores', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('entity_id')->constrained('core.entities');
            $table->smallInteger('variable_id');
            $table->double('score')->nullable();
            // Always stored, even with no score: "0 of 4 measured" is itself worth showing.
            $table->double('coverage');
            $table->double('confidence')->nullable();
            $table->text('status');
            // Only V1 and V2 have a gate (1.1, 2.1); null for V3–V5 (decision 7 Oct).
            $table->text('gate_status')->nullable();
            $table->text('gate_failed_sub_id')->nullable();
            $table->integer('weight_version');
            $table->text('engine_version');
            $table->timestampTz('computed_at')->useCurrent();
            // The sub-variable rows and weights this score was rolled up from.
            $table->jsonb('inputs');
        });

        // Panels and tiles ask for "the newest score per entity × variable"; newest first makes that one index read.
        DB::statement(
            'CREATE INDEX variable_scores_entity_variable_computed_index
             ON scores.variable_scores (entity_id, variable_id, computed_at DESC)'
        );

        $this->addAllowedValuesCheck();
        $this->addScoreMatchesStatusCheck();
        $this->addGateMatchesStatusCheck();

        // Append-only: INSERT, never UPDATE, so history cannot be rewritten (ADR-004a §2).
        DB::statement('GRANT INSERT ON scores.variable_scores TO nv_app');
    }

    /**
     * Drop the table. Its GRANT, CHECKs and index go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores.variable_scores');
    }

    /**
     * Five variables, the three statuses and gate states of Bible §6.3, and the usual ranges.
     */
    private function addAllowedValuesCheck(): void
    {
        DB::statement(
            "ALTER TABLE scores.variable_scores ADD CONSTRAINT variable_scores_allowed_values_check
             CHECK (
                 variable_id BETWEEN 1 AND 5
                 AND status IN ('scored', 'cannot_assess', 'provisional')
                 AND gate_status IN ('passed', 'failed', 'unmeasured')
                 AND score BETWEEN 0 AND 100
                 AND coverage BETWEEN 0 AND 1
                 AND confidence BETWEEN 0 AND 1
                 AND weight_version >= 1
             )"
        );
    }

    /**
     * cannot_assess has no score. Any other status has a score, and a score never appears without
     * its confidence beside it (CLAUDE.md §4; coverage is a NOT NULL column).
     */
    private function addScoreMatchesStatusCheck(): void
    {
        DB::statement(
            "ALTER TABLE scores.variable_scores ADD CONSTRAINT variable_scores_score_matches_status_check
             CHECK (
                 (status = 'cannot_assess' AND score IS NULL)
                 OR (status IN ('scored', 'provisional') AND score IS NOT NULL AND confidence IS NOT NULL)
             )"
        );
    }

    /**
     * The three-state gate of Bible §6.3. IS [NOT] DISTINCT FROM treats a null gate (V3–V5) as
     * "not unmeasured" and "not failed", where a plain = would return NULL and let the row through.
     */
    private function addGateMatchesStatusCheck(): void
    {
        DB::statement(
            "ALTER TABLE scores.variable_scores ADD CONSTRAINT variable_scores_gate_matches_status_check
             CHECK (
                 (variable_id IN (1, 2)) = (gate_status IS NOT NULL)
                 AND (gate_status IS NOT DISTINCT FROM 'unmeasured') = (status = 'provisional')
                 AND (gate_status IS DISTINCT FROM 'failed' OR status = 'cannot_assess')
                 AND (gate_status IS NOT DISTINCT FROM 'failed') = (gate_failed_sub_id IS NOT NULL)
             )"
        );
    }
};
