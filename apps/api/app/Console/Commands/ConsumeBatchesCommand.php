<?php

// `php artisan engine:consume-batches` — the long-running rollup worker. Supervisor keeps it up on
// Box A (infra/supervisor/rollup-consumer.conf); it rolls up entities as the signal runner writes
// their sub-variable scores. `--once` handles one round and exits, for tests and debugging.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engine\BatchWrittenConsumer;
use Illuminate\Console\Command;

/**
 * Runs BatchWrittenConsumer in a loop. Implements work pack K-10 ("Supervisor-run stream consumer")
 * and K2.2 (rollup runs without a manual command).
 */
class ConsumeBatchesCommand extends Command
{
    /** How long one read waits for a new message before checking for stale ones again. */
    private const DEFAULT_BLOCK_MILLISECONDS = 5000;

    protected $signature = 'engine:consume-batches
        {--consumer-name= : Name of this reader in the rollup group (default: host-pid)}
        {--block-ms='.self::DEFAULT_BLOCK_MILLISECONDS.' : How long each read waits for a new message}
        {--once : Handle one round, then exit}';

    protected $description = 'Roll up entities as signals.batch_written messages arrive';

    public function handle(BatchWrittenConsumer $consumer): int
    {
        $consumerName = (string) ($this->option('consumer-name') ?: gethostname().'-'.getmypid());
        $blockMilliseconds = (int) $this->option('block-ms');
        $isOnce = (bool) $this->option('once');

        $consumer->createGroupIfMissing();
        $this->info("Reading signals.batch_written as {$consumerName}.");

        do {
            $handledCount = $consumer->processOnce($consumerName, $blockMilliseconds);
            if ($handledCount > 0) {
                $this->info("Handled {$handledCount} message(s).");
            }
        } while (! $isOnce);

        return self::SUCCESS;
    }
}
