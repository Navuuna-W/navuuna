<?php

// Laravel's login tables: users, password reset tokens and sessions.
// They live in `public` (framework tables, ADR-004a §1) and only Laravel touches them.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the login tables in `public`.
     */
    public function up(): void
    {
        Schema::create('public.users', function (Blueprint $table) {
            $table->id();
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
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Drop the tables.
     */
    public function down(): void
    {
        Schema::dropIfExists('public.sessions');
        Schema::dropIfExists('public.password_reset_tokens');
        Schema::dropIfExists('public.users');
    }
};
