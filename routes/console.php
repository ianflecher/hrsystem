<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * A nightly dump, kept for a fortnight.
 *
 * This only runs if something is running the scheduler: on Windows, a Task
 * Scheduler entry that runs `php artisan schedule:run` every minute; on Linux,
 * the usual cron line. Without that, run `php artisan db:backup` by hand - and
 * copy the dumps somewhere off this machine either way.
 */
Schedule::command('db:backup --keep=14')->dailyAt('01:30')->withoutOverlapping();

Artisan::command('hris:health', function () {
    $exit = app(\App\Console\Commands\SystemHealth::class)->handle();
    if ($exit !== 0) $this->fail('HRIS health check failed.');
})->purpose('Check the HRIS database and required tables.');
