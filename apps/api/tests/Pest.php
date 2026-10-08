<?php

// Pest setup: every Feature test boots the Laravel app through Tests\TestCase.
// Database tests talk to real PostGIS (see phpunit.xml), never SQLite.

declare(strict_types=1);

use Tests\TestCase;

require_once __DIR__.'/Support/core_rows.php';
require_once __DIR__.'/Support/raw_rows.php';
require_once __DIR__.'/Support/flag_rows.php';
require_once __DIR__.'/Support/engine_inputs.php';
require_once __DIR__.'/Support/score_rows.php';
require_once __DIR__.'/Support/evidence_rows.php';
require_once __DIR__.'/Support/finding_scenarios.php';

pest()->extend(TestCase::class)->in('Feature');
