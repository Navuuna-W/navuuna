<?php

// Closes a gap in scores.sub_variable_scores found during the K-10 Done check: a measured row
// with source_ids = '{NULL}' passed, because cardinality() counts the NULL as one source. Every
// source id must point at a real row, or the score cannot be traced back (Bible §7.3).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * No NULL anywhere in source_ids. array_position() finds NULL elements (it compares with
     * IS NOT DISTINCT FROM), so the check is NULL-safe; an empty array has none and still passes.
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE scores.sub_variable_scores ADD CONSTRAINT sub_variable_scores_source_ids_no_null_check
             CHECK (array_position(source_ids, NULL) IS NULL)'
        );
    }

    /**
     * Drop the constraint, back to the CHECKs of 2026_10_07_000400.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE scores.sub_variable_scores DROP CONSTRAINT sub_variable_scores_source_ids_no_null_check');
    }
};
