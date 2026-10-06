<?php

// Creates core.sources: every place data comes from (a scraped site, a raster, a community upload).
// Python ingest registers sources before it loads anything, so only `nv_ingest` writes here.

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
     * Implements Bible §10 (core.sources) and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('core.sources', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            // Unique so a loader can find its own source again on the next run.
            $table->text('name')->unique();
            $table->text('kind');
            $table->text('base_url')->nullable();
            $table->text('licence');
            $table->text('attribution');
            $table->text('schedule')->nullable();
            $table->boolean('active')->default(true);
        });

        DB::statement(
            "ALTER TABLE core.sources ADD CONSTRAINT sources_kind_check
             CHECK (kind IN ('scrape', 'raster', 'vector', 'upload', 'community'))"
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON core.sources TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECK go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('core.sources');
    }
};
