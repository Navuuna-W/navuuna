<?php

// Creates flags.outcomes: what turned out to be true about a published finding, recorded later
// by a person (outcomes:record, K-17). It is how we learn whether findings were right.
// Only Laravel (`nv_app`) writes here.

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
     * Implements Bible §10 (flags.outcomes) and ADR-004a appendix (writer: nv_app).
     */
    public function up(): void
    {
        Schema::create('flags.outcomes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('flag_id')->index()->constrained('flags.flags');
            $table->text('outcome');
            // What the outcome rests on, e.g. "site visit 12 Oct, pump replaced".
            $table->text('evidence');
            $table->foreignUuid('recorded_by')->constrained('public.users');
            $table->timestampTz('at')->useCurrent();
        });

        DB::statement(
            "ALTER TABLE flags.outcomes ADD CONSTRAINT outcomes_allowed_values_check
             CHECK (outcome IN ('confirmed', 'refuted', 'partial', 'unknown') AND btrim(evidence) <> '')"
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON flags.outcomes TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and CHECK go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags.outcomes');
    }
};
