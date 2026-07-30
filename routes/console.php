<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-check --force')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn (): bool => (bool) config('updater.enabled'));

Schedule::command('messages:dispatch-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('messages:enforce-retention')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

if (config('updater.install_enabled')) {
    Schedule::command('app:update --pending')
        ->everyMinute()
        ->withoutOverlapping(60)
        ->onOneServer();
}
