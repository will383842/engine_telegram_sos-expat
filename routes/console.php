<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('telegram:process-queue')->everyMinute()->withoutOverlapping();
Schedule::command('telegram:daily-report')->dailyAt('19:00')->timezone('Europe/Paris');
Schedule::command('telegram:cleanup')->dailyAt('03:00');

// Trustpilot weekly report — every Monday at 09:00 Paris time
Schedule::command('trustpilot:weekly-report')
    ->weeklyOn(
        (int) config('trustpilot.report_day', 1),
        config('trustpilot.report_time', '09:00')
    )
    ->timezone('Europe/Paris')
    ->withoutOverlapping();
