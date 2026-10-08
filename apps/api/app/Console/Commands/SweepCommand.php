<?php

// `php artisan engine:sweep` — rolls up every entity whose variable scores are behind its
// sub-variable scores. The scheduler runs it every 10 minutes (routes/console.php).

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engine\ReconciliationSweep;
use Illuminate\Console\Command;

/**
 * Runs ReconciliationSweep once. Implements ADR-004a §4 and work pack K-10 ("reconciliation schedule").
 */
class SweepCommand extends Command
{
    protected $signature = 'engine:sweep';

    protected $description = 'Roll up entities whose variable scores are older than their sub-variable scores';

    public function handle(ReconciliationSweep $sweep): int
    {
        $rolledUpCount = $sweep->run();
        $this->info("Swept {$rolledUpCount} stale entities.");

        return self::SUCCESS;
    }
}
