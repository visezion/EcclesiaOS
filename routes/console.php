<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-check')
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn (): bool => (bool) config('updater.enabled'));

if (config('updater.install_enabled')) {
    Schedule::command('app:update --pending')
        ->everyMinute()
        ->withoutOverlapping(60)
        ->onOneServer();
}
