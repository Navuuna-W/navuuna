<?php

// Creates flags.evidence_packs: the evidence behind one finding — the record, observation,
// document and satellite rows it rests on, and a plain-language narrative. Reviewers decide from
// it, and a published finding shows it. The findings engine (`nv_app`, K-13) writes it.

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
     * Implements Bible §6.5 (V2 emits a flag with an evidence pack, never a bare number),
     * Bible §10 (flags.evidence_packs) and ADR-004a appendix (writer: nv_app).
     */
    public function up(): void
    {
        Schema::create('flags.evidence_packs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // One pack per finding; a regenerated pack replaces the old one in place.
            $table->foreignUuid('flag_id')->unique()->constrained('flags.flags');
            $table->text('narrative');
            $table->timestampTz('generated_at')->useCurrent();
            $table->text('adapter_version');
        });

        // Blueprint has no array column type. No foreign keys are possible on arrays; the ids
        // come from the source_ids of the score that raised the finding.
        DB::statement(
            "ALTER TABLE flags.evidence_packs
             ADD COLUMN record_ids uuid[] NOT NULL DEFAULT '{}',
             ADD COLUMN observation_ids uuid[] NOT NULL DEFAULT '{}',
             ADD COLUMN document_ids uuid[] NOT NULL DEFAULT '{}',
             ADD COLUMN eo_stat_ids uuid[] NOT NULL DEFAULT '{}'"
        );

        // A finding is a claim about a named party, so its pack must point at real evidence.
        DB::statement(
            "ALTER TABLE flags.evidence_packs ADD CONSTRAINT evidence_packs_has_evidence_check
             CHECK (
                 cardinality(record_ids) + cardinality(observation_ids)
                 + cardinality(document_ids) + cardinality(eo_stat_ids) > 0
                 AND btrim(narrative) <> ''
             )"
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON flags.evidence_packs TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and CHECK go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags.evidence_packs');
    }
};
