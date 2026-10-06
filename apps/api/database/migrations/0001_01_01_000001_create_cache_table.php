<?php

// Laravel's database cache and cache locks. Framework tables, so they live in `public`
// and only Laravel (`nv_app`) touches them (ADR-004a §1).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('public.cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('public.cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        // Cache entries expire, so DELETE is allowed on these framework tables (ADR-004a §2).
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON public.cache, public.cache_locks TO nv_app');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public.cache');
        Schema::dropIfExists('public.cache_locks');
    }
};
