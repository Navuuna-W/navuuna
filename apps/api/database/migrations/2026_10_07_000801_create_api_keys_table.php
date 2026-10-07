<?php

// API keys for machines that call the JSON API with X-Api-Key (work pack K-11, NFR-05).
// Framework table in `public` (ADR-004a §1); `keys:issue` writes it; the X-Api-Key guard reads it.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table and grant it to Laravel's role.
     *
     * Only the SHA-256 of a key is stored (NFR-05: API keys hashed). The plain key is shown
     * once by `keys:issue` and never saved.
     */
    public function up(): void
    {
        Schema::create('public.api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('user_id')->constrained('public.users');
            $table->text('name');
            $table->char('key_hash', 64)->unique();
            $table->timestampsTz();
        });

        // No DELETE: keys don't expire, and revoking one later is an UPDATE (ADR-004a §2).
        DB::statement('GRANT SELECT, INSERT, UPDATE ON public.api_keys TO nv_app');
    }

    /**
     * Drop the table. Its GRANT and indexes go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('public.api_keys');
    }
};
