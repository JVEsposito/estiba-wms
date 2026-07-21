<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\TelescopeServiceProvider;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (app()->environment('local') &&
    class_exists(TelescopeServiceProvider::class)) {
    Schedule::command('telescope:prune --hours=48')->dailyAt('02:00');
}
