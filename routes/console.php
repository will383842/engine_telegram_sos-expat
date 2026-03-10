<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('telegram:process-queue')->everyMinute()->withoutOverlapping();
Schedule::command('telegram:daily-report')->dailyAt('19:00')->timezone('Europe/Paris');
Schedule::command('telegram:cleanup')->dailyAt('03:00');
