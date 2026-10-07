<?php

// Gives every user exactly one role (FR-20): admin, analyst, viewer or api_client.
// Runs after the framework users table exists; the Role enum (app/Auth/Role.php) reads this column.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the role column and limit it to the four FR-20 roles.
     *
     * No default: whoever creates an account must choose the role (PRD 6.2: accounts are
     * created by us), so nobody gets access by accident.
     */
    public function up(): void
    {
        Schema::table('public.users', function (Blueprint $table) {
            $table->text('role');
        });

        DB::statement(
            "ALTER TABLE public.users ADD CONSTRAINT users_role_check
             CHECK (role IN ('admin', 'analyst', 'viewer', 'api_client'))"
        );
    }

    /**
     * Drop the column. Its CHECK goes with it.
     */
    public function down(): void
    {
        Schema::table('public.users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
