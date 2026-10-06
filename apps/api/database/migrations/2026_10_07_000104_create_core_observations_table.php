<?php

// Creates core.observations: something seen about one entity at one time, such as a community
// report that a water point is dry. Laravel takes them in (Austine), so only `nv_app` writes here.

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
     * Implements Bible §10 (core.observations) and ADR-004a appendix (writer: nv_app).
     */
    public function up(): void
    {
        Schema::create('core.observations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('entity_id')->constrained('core.entities');
            $table->foreignUuid('source_id')->constrained('core.sources');
            $table->text('kind');
            $table->jsonb('payload');
            $table->timestampTz('observed_at');
            $table->timestampTz('ingested_at')->useCurrent();
            // No foreign key yet: whether every contributor is a public.users row is decided in K-11a.
            $table->uuid('contributor_id')->nullable();
            $table->foreignUuid('consent_id')->nullable()->constrained('core.consents');

            $table->index(['entity_id', 'observed_at']);
        });

        // A person's observation is only stored with their consent beside it.
        DB::statement(
            'ALTER TABLE core.observations ADD CONSTRAINT observations_contributor_has_consent_check
             CHECK (contributor_id IS NULL OR consent_id IS NOT NULL)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON core.observations TO nv_app');
    }

    /**
     * Drop the table. Its GRANT, CHECK and foreign keys go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('core.observations');
    }
};
