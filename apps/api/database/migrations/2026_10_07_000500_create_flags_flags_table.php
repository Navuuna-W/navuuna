<?php

// Creates flags.flags: one finding per entity × V2 sub-variable — a gap between what a record
// declares and what is observed. Findings start held and reach the public only through human review
// (K-14). Laravel's findings engine and FlagWorkflow write it (`nv_app`).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table, its CHECKs, its indexes and its one writer's GRANT.
     *
     * Implements Bible §6.5 and §10 (flags.flags), ADR-010 DEC-09 (one open finding per
     * entity × sub-variable) and ADR-004a appendix (writer: nv_app).
     */
    public function up(): void
    {
        Schema::create('flags.flags', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('entity_id')->constrained('core.entities');
            $table->text('sub_id');
            $table->text('severity');
            $table->text('state')->default('detected');
            // What the record says and what was seen, built from the rows behind the score (K-13).
            $table->jsonb('declared');
            $table->jsonb('observed');
            // Size of the gap in the sub-variable's own unit; null when the gap is a yes/no.
            $table->double('gap')->nullable();
            $table->timestampTz('detected_at')->useCurrent();
            $table->timestampTz('state_changed_at')->useCurrent();
        });

        DB::statement(
            "ALTER TABLE flags.flags ADD CONSTRAINT flags_allowed_values_check
             CHECK (
                 sub_id IN ('2.1', '2.2', '2.3', '2.4', '2.5')
                 AND severity IN ('low', 'medium', 'high')
                 AND state IN ('detected', 'held', 'explanation_checked', 'published',
                               'dismissed', 'contested', 'resolved')
             )"
        );

        // DEC-09 rule 5: at most one open finding per entity × sub-variable. Dismissed and
        // resolved findings are closed, so a new gap after them raises a new finding.
        DB::statement(
            "CREATE UNIQUE INDEX flags_one_open_per_entity_sub_index
             ON flags.flags (entity_id, sub_id)
             WHERE state NOT IN ('dismissed', 'resolved')"
        );
        // The review queue lists findings by state, newest first.
        DB::statement('CREATE INDEX flags_state_detected_index ON flags.flags (state, detected_at DESC)');

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON flags.flags TO nv_app');
    }

    /**
     * Drop the table. Its GRANT, CHECK and indexes go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags.flags');
    }
};
