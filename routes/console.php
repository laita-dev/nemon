<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| OMIE Real-Time Price Synchronization - Scheduled Tasks
|--------------------------------------------------------------------------
|
| OMIE publishes day-ahead prices (D+1) approximately at 13:30 CET every day.
| We fetch hourly to ensure the data is captured as soon as it is published.
|
| To activate this schedule, run the Laravel scheduler:
|   - Windows (Task Scheduler): php artisan schedule:run
|   - Linux (Cron): * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
|
*/

// Fetch today's and tomorrow's OMIE prices every hour
Schedule::command('omie:fetch')->hourly();

// Fetch again at 13:30 and 14:00 CET specifically (publication window)
Schedule::command('omie:fetch')->dailyAt('13:30');
Schedule::command('omie:fetch')->dailyAt('14:00');

// Daily catch-up at midnight to fill any missing data
Schedule::command('omie:fetch')->dailyAt('00:05');
