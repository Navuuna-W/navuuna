<?php

// Creates lens.lens_scores: the current lens score and map colour for each entity under each lens.
// Tiles read it hundreds of times a pan, so it holds one row per lens × entity, updated in place.
// Laravel's lens application (`nv_app`) writes it.

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
     * Implements Bible §10 (lens.lens_scores), CLAUDE.md §4 (no score without coverage and
     * confidence) and ADR-004a appendix (writer: nv_app). coverage and confidence are DEC-12's
     * proposed columns, built ahead of Austine's ADR (stub register in the progress file).
     */
    public function up(): void
    {
        Schema::create('lens.lens_scores', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('entity_id')->constrained('core.entities');
            $table->foreignUuid('lens_id')->constrained('lens.lenses');
            // Null when the lens cannot score the entity; colour_class then says so.
            $table->double('score')->nullable();
            $table->text('colour_class');
            $table->double('coverage');
            $table->double('confidence')->nullable();
            $table->timestampTz('computed_at')->useCurrent();

            // One row per lens × entity; tiles look rows up by lens first.
            $table->unique(['lens_id', 'entity_id']);
        });

        DB::statement(
            'ALTER TABLE lens.lens_scores ADD CONSTRAINT lens_scores_ranges_check
             CHECK (score BETWEEN 0 AND 100 AND coverage BETWEEN 0 AND 1 AND confidence BETWEEN 0 AND 1)'
        );
        DB::statement(
            'ALTER TABLE lens.lens_scores ADD CONSTRAINT lens_scores_score_has_confidence_check
             CHECK (score IS NULL OR confidence IS NOT NULL)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON lens.lens_scores TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('lens.lens_scores');
    }
};
