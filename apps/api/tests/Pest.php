<?php

// Pest setup: every Feature test boots the Laravel app through Tests\TestCase.
// Database tests talk to real PostGIS (see phpunit.xml), never SQLite.

declare(strict_types=1);

use Tests\TestCase;

require_once __DIR__.'/Support/core_rows.php';

pest()->extend(TestCase::class)->in('Feature');
