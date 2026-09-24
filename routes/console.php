<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --queue=default --stop-when-empty --timeout=900 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(15)
    ->name('queue-work')
    ->appendOutputTo(storage_path('logs/queue.log'));

Schedule::command('donations:prune-pending')
    ->hourly()
    ->withoutOverlapping()
    ->name('prune-pending-donations');

Schedule::command('birthdays:announce')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->name('announce-birthdays');

Schedule::command('memberships:remind-unpaid')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->name('remind-unpaid-memberships');
