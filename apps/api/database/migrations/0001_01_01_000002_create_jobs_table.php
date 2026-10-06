<?php

// Laravel's queue tables: waiting jobs, job batches and failed jobs. Framework tables, so they
// live in `public` and only Laravel's workers (`nv_app`) touch them (ADR-004a §1).

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
        Schema::create('public.jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('public.job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('public.failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
        });

        // Finished jobs are deleted by the worker, so DELETE is allowed here (ADR-004a §2).
        // jobs and failed_jobs have bigint IDs, so their sequences need USAGE too.
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON public.jobs, public.job_batches, public.failed_jobs TO nv_app');
        DB::statement('GRANT USAGE ON SEQUENCE public.jobs_id_seq, public.failed_jobs_id_seq TO nv_app');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public.jobs');
        Schema::dropIfExists('public.job_batches');
        Schema::dropIfExists('public.failed_jobs');
    }
};
