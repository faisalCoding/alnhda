<?php

use Carbon\CarbonInterface;
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

/**
 * The Saudi week runs Saturday to Thursday. The list is opened just before the
 * Saturday brief goes out so the brief always has something to describe, and
 * the summary follows on Thursday evening once the week's work is in.
 */
Schedule::command('weekly-tasks:generate')->weeklyOn(CarbonInterface::SATURDAY, '07:00')->withoutOverlapping();
Schedule::command('weekly-tasks:report opening')->weeklyOn(CarbonInterface::SATURDAY, '08:00')->withoutOverlapping();
Schedule::command('weekly-tasks:report closing')->weeklyOn(CarbonInterface::THURSDAY, '16:00')->withoutOverlapping();
