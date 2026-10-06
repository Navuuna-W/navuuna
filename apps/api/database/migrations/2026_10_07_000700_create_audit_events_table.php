<?php

// Creates audit.events: who did what to which row, with the row before and after. Every finding
// transition and admin action writes one (K-14). Rows are never edited, so the record of who
// changed what cannot be rewritten. Only Laravel (`nv_app`) writes here.

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
     * Implements Bible §10 (audit.events) and ADR-004a appendix (nv_app, INSERT only).
     */
    public function up(): void
    {
        Schema::create('audit.events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // Null means the system acted, e.g. E8 auto-resolve (ADR-010).
            $table->foreignUuid('user_id')->nullable()->constrained('public.users');
            $table->text('action');
            $table->text('target_table');
            $table->uuid('target_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampTz('at')->useCurrent();

            // "Show the history of this finding" reads every event for one row, oldest first.
            $table->index(['target_table', 'target_id', 'at']);
        });

        DB::statement(
            "ALTER TABLE audit.events ADD CONSTRAINT events_action_not_blank_check
             CHECK (btrim(action) <> '' AND btrim(target_table) <> '')"
        );

        // Append-only: INSERT, never UPDATE, so history cannot be rewritten (ADR-004a §2).
        DB::statement('GRANT INSERT ON audit.events TO nv_app');
    }

    /**
     * Drop the table. Its GRANT, CHECK and index go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit.events');
    }
};
