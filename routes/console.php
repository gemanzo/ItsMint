<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Daily data synchronization sequence
// 1. Mirror CardTrader database (structure + base data)
Schedule::command('itsmint:mirror-ct-database')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->runInBackground();

// 2. Mirror OneLog data
Schedule::command('itsmint:mirror-onelog --all --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// 3. Match blueprints with OneLog data
Schedule::command('itsmint:match-blueprints-onelog')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('itsmint:classify-blueprints --save')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// 3. Continuous price updates every 6 hours
Schedule::command('itsmint:queue-price-updates')
    ->everySixHours()
    ->withoutOverlapping();
