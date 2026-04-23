<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('digest:send')
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'));

Schedule::command('reminders:process')
    ->everyThirtyMinutes();
