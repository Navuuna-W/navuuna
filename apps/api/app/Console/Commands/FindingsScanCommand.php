<?php

// `php artisan findings:scan {entity}` or `findings:scan --all` — runs the findings engine by hand.
// Normally it runs after every rollup (UpdateFindingsAfterEntityScored); this is for the Done check
// and for a re-run after Devyan's findings.yml changes.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Findings\RaiseFindingsForEntity;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

/**
 * Raises and holds findings for one entity, or every entity that is not retired. Implements
 * work pack K-13.
 */
class FindingsScanCommand extends Command
{
    /** Entity ids read from the database per round trip in --all. */
    private const ENTITY_PAGE_SIZE = 500;

    protected $signature = 'findings:scan
        {entity? : The id of one entity to scan}
        {--all : Scan every entity that is not retired}';

    protected $description = 'Raise and hold the findings the newest sub-variable scores call for';

    /**
     * Scan what was asked for and report how many findings were raised. Non-zero exit on bad input.
     */
    public function handle(RaiseFindingsForEntity $raiseFindingsForEntity, ConnectionInterface $database): int
    {
        $entityId = $this->argument('entity');
        $isAll = (bool) $this->option('all');

        if ($isAll === ($entityId !== null)) {
            $this->error('Give one entity id, or --all, but not both.');

            return self::FAILURE;
        }

        if ($isAll) {
            return $this->scanAll($raiseFindingsForEntity, $database);
        }

        $raisedCount = $raiseFindingsForEntity->raise((string) $entityId);
        $this->info("Raised {$raisedCount} findings for 1 entity.");

        return self::SUCCESS;
    }

    private function scanAll(RaiseFindingsForEntity $raiseFindingsForEntity, ConnectionInterface $database): int
    {
        $raisedCount = 0;
        $scannedCount = 0;
        $database->table('core.entities')
            ->whereNull('retired_at')
            ->select('id')
            ->chunkById(self::ENTITY_PAGE_SIZE, function ($entities) use ($raiseFindingsForEntity, &$raisedCount, &$scannedCount): void {
                foreach ($entities as $entity) {
                    $raisedCount += $raiseFindingsForEntity->raise((string) $entity->id);
                    $scannedCount++;
                }
            });

        $this->info("Raised {$raisedCount} findings across {$scannedCount} entities.");

        return self::SUCCESS;
    }
}
