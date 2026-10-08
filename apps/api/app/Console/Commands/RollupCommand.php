<?php

// `php artisan engine:rollup {entity}` or `engine:rollup --all` — rolls up by hand. Normally the
// stream consumer (K-10c) and the 10-minute sweep (K-10d) do this without anyone typing a command.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engine\RollupEntity;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

/**
 * Rolls up one entity, or every entity that is not retired. Implements work pack K-10
 * (`engine:rollup --all`, which must finish in under 10 minutes for the demo data set).
 */
class RollupCommand extends Command
{
    /** Entity ids read from the database per round trip in --all. */
    private const ENTITY_PAGE_SIZE = 500;

    protected $signature = 'engine:rollup
        {entity? : The id of one entity to roll up}
        {--all : Roll up every entity that is not retired}';

    protected $description = 'Roll sub-variable scores up into the five variable scores';

    /**
     * Roll up what was asked for. Non-zero exit on bad input or an unknown/retired entity.
     */
    public function handle(RollupEntity $rollupEntity, ConnectionInterface $database): int
    {
        $entityId = $this->argument('entity');
        $isAll = (bool) $this->option('all');

        if ($isAll === ($entityId !== null)) {
            $this->error('Give one entity id, or --all, but not both.');

            return self::FAILURE;
        }

        if ($isAll) {
            return $this->rollUpAll($rollupEntity, $database);
        }

        if (! $rollupEntity->rollUp((string) $entityId)) {
            $this->error('No entity with that id, or it is retired.');

            return self::FAILURE;
        }

        $this->info('Rolled up 1 entity.');

        return self::SUCCESS;
    }

    private function rollUpAll(RollupEntity $rollupEntity, ConnectionInterface $database): int
    {
        $rolledUpCount = 0;
        $database->table('core.entities')
            ->whereNull('retired_at')
            ->select('id')
            ->chunkById(self::ENTITY_PAGE_SIZE, function ($entities) use ($rollupEntity, &$rolledUpCount): void {
                foreach ($entities as $entity) {
                    $rollupEntity->rollUp((string) $entity->id);
                    $rolledUpCount++;
                }
            });

        $this->info("Rolled up {$rolledUpCount} entities.");

        return self::SUCCESS;
    }
}
