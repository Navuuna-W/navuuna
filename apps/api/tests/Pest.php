<?php

// Pest setup: every Feature test boots the Laravel app through Tests\TestCase.
// Database tests talk to real PostGIS (see phpunit.xml), never SQLite.

declare(strict_types=1);
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
