<?php

// Creates core.consents: a contributor's agreement to share observations, and when they withdrew it.
// Laravel records consent in the observation flow (Austine), so only `nv_app` writes here.

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
     * Implements Bible §10 (core.consents) and ADR-004a appendix (writer: nv_app).
     */
    public function up(): void
    {
        Schema::create('core.consents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // No foreign key yet: whether every contributor is a public.users row is decided in K-11a.
            $table->uuid('contributor_id');
            $table->text('scope');
            $table->timestampTz('granted_at')->useCurrent();
            // Consent is withdrawn by setting this, never by deleting the row (ADR-004a §2).
            $table->timestampTz('withdrawn_at')->nullable();
            // Which wording of the consent text the contributor agreed to.
            $table->text('text_version');
        });

        DB::statement(
            'ALTER TABLE core.consents ADD CONSTRAINT consents_withdrawn_after_granted_check
             CHECK (withdrawn_at IS NULL OR withdrawn_at >= granted_at)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON core.consents TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and CHECK go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('core.consents');
    }
};
