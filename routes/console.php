<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * The gateway holds delivery acknowledgements in memory only, so they are
 * pulled into the database regularly. withoutOverlapping keeps a slow run from
 * stacking on the next tick.
 */
Schedule::command('whatsapp:sync-acks')->everyFiveMinutes()->withoutOverlapping();
