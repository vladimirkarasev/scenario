<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('directories:run-scheduled-imports')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('actions:run-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('dictionaries:sync')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('dictionaries:warmup-cache')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('embed-auth:prune-tokens')
    ->hourly()
    ->withoutOverlapping();
