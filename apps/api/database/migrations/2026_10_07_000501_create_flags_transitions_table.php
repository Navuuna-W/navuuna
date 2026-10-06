<?php

// Creates flags.transitions: every change of a finding's state, with who made it, when and why.
// FlagWorkflow (K-14) appends a row for each change; rows are never edited, so the review history
// of a finding cannot be rewritten. Only Laravel (`nv_app`) writes here.

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
     * Implements Bible §6.5 (every transition records who, when, why), Bible §10 (flags.transitions)
     * and ADR-004a appendix (nv_app, INSERT only). Which transitions are legal is FlagWorkflow's
     * job (K-14), not the database's.
     */
    public function up(): void
    {
        Schema::create('flags.transitions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('flag_id')->constrained('flags.flags');
            // Null only for the first row, when the finding is detected.
            $table->text('from_state')->nullable();
            $table->text('to_state');
            // Null means the system made the change, e.g. E8 auto-resolve (ADR-010).
            $table->foreignUuid('user_id')->nullable()->constrained('public.users');
            $table->text('note');
            $table->timestampTz('at')->useCurrent();

            $table->index(['flag_id', 'at']);
        });

        DB::statement(
            "ALTER TABLE flags.transitions ADD CONSTRAINT transitions_states_check
             CHECK (
                 to_state IN ('detected', 'held', 'explanation_checked', 'published',
                              'dismissed', 'contested', 'resolved')
                 AND (from_state IS NULL OR from_state IN ('detected', 'held', 'explanation_checked',
                      'published', 'dismissed', 'contested', 'resolved'))
                 AND from_state IS DISTINCT FROM to_state
             )"
        );
        // A note is mandatory on every transition (K-14, ADR-010 DEC-10).
        DB::statement(
            "ALTER TABLE flags.transitions ADD CONSTRAINT transitions_note_not_blank_check
             CHECK (btrim(note) <> '')"
        );

        // Append-only: INSERT, never UPDATE, so history cannot be rewritten (ADR-004a §2).
        DB::statement('GRANT INSERT ON flags.transitions TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags.transitions');
    }
};
