<?php

// Laravel's login tables: users, password reset tokens and sessions.
// They live in `public` (framework tables, ADR-004a §1) and only Laravel (`nv_app`) touches them.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the tables and grant them to Laravel's role.
     *
     * Users get UUIDv7 IDs like every other table (ADR-012 §2), so audit rows and observations
     * can point at a user with the same column type they use for everything else.
     */
    public function up(): void
    {
        Schema::create('public.users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('public.password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('public.sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Sessions and reset tokens expire, so these are the only tables where DELETE is allowed
        // (ADR-004a §2).
        DB::statement(
            'GRANT SELECT, INSERT, UPDATE, DELETE ON public.users, public.password_reset_tokens, public.sessions TO nv_app'
        );
    }

    /**
     * Drop the tables. Their GRANTs go with them.
     */
    public function down(): void
    {
        Schema::dropIfExists('public.sessions');
        Schema::dropIfExists('public.password_reset_tokens');
        Schema::dropIfExists('public.users');
    }
};
