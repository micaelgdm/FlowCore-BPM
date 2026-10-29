<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('flowcore:sync-process-instances --limit=25')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('flowcore:notify-task-deadlines --limit=25')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
