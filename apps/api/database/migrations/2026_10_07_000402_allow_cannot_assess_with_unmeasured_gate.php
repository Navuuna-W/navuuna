<?php

// Amends the gate CHECK on scores.variable_scores for one case the rollup meets (K-10): a gate
// that was not measured AND no contributor measured. There is nothing to score, so the row is
// cannot_assess, but it keeps gate_status 'unmeasured' so the UI can say why (Bible §6.3).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Before: unmeasured gate ⇔ provisional. After: provisional ⇒ unmeasured gate, and an
     * unmeasured gate ⇒ provisional or cannot_assess. Failed-gate rules are unchanged.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE scores.variable_scores DROP CONSTRAINT variable_scores_gate_matches_status_check');

        // IS [NOT] DISTINCT FROM treats a null gate (V3–V5) as "not unmeasured" and "not failed",
        // where a plain = would return NULL and let the row through.
        DB::statement(
            "ALTER TABLE scores.variable_scores ADD CONSTRAINT variable_scores_gate_matches_status_check
             CHECK (
                 (variable_id IN (1, 2)) = (gate_status IS NOT NULL)
                 AND (status <> 'provisional' OR gate_status IS NOT DISTINCT FROM 'unmeasured')
                 AND (gate_status IS DISTINCT FROM 'unmeasured' OR status IN ('provisional', 'cannot_assess'))
                 AND (gate_status IS DISTINCT FROM 'failed' OR status = 'cannot_assess')
                 AND (gate_status IS NOT DISTINCT FROM 'failed') = (gate_failed_sub_id IS NOT NULL)
             )"
        );
    }

    /**
     * Put back the original CHECK from 2026_10_07_000401. Fails if a cannot_assess row with an
     * unmeasured gate exists, which is right: those rows would break the old rule.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE scores.variable_scores DROP CONSTRAINT variable_scores_gate_matches_status_check');

        DB::statement(
            "ALTER TABLE scores.variable_scores ADD CONSTRAINT variable_scores_gate_matches_status_check
             CHECK (
                 (variable_id IN (1, 2)) = (gate_status IS NOT NULL)
                 AND (gate_status IS NOT DISTINCT FROM 'unmeasured') = (status = 'provisional')
                 AND (gate_status IS DISTINCT FROM 'failed' OR status = 'cannot_assess')
                 AND (gate_status IS NOT DISTINCT FROM 'failed') = (gate_failed_sub_id IS NOT NULL)
             )"
        );
    }
};
