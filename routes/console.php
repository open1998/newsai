<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scrape all active Sri Lankan news sources every 5 minutes.
 *
 * withoutOverlapping() ensures a slow scrape run does not stack up
 * concurrent runs if the queue takes longer than 5 minutes to process.
 */
Schedule::command('news:scrape')
    ->everyFiveMinutes()
    ->withoutOverlapping();
