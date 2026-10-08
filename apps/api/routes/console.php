<?php

// Artisan closures and the scheduler. Box A runs `php artisan schedule:run` every minute from cron
// (or `schedule:work` locally), which starts the entries below when they are due.

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ADR-004a §4: catch any entity a lost stream message left behind. withoutOverlapping: a slow
// sweep is never started twice at once.
Schedule::command('engine:sweep')->everyTenMinutes()->withoutOverlapping();
