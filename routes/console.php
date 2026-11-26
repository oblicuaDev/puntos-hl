<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('points:expire-batches')->dailyAt('02:00');
Schedule::command('checkout:cancel-abandoned')->everyFiveMinutes();
Schedule::command('points:sync-billing-sheet')->dailyAt('06:00')->timezone('America/Bogota')->withoutOverlapping();
