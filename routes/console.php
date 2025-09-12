<?php

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

Schedule::command('itsmint:classify-blueprints --save')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// 3. Match blueprints with OneLog data
Schedule::command('itsmint:match-blueprints-onelog')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// 4. Index blueprints into Elasticsearch
Schedule::command('elasticsearch:index-blueprints --fresh')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// 5. Continuous price updates every 6 hours
Schedule::command('itsmint:queue-price-updates')
    ->everySixHours()
    ->withoutOverlapping();

// 6. Backup the database
Schedule::command('backup:run --only-db')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// 7. Refresh latest card prices
Schedule::command('itsmint:refresh-latest')
    ->hourly()
    ->withoutOverlapping();

// 8. Ingest Cardmarket price guide daily before queueing price updates
Schedule::command('itsmint:download-cardmarket-guide')
    ->dailyAt('01:25')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('itsmint:ingest-cardmarket --limit=0')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground();

// clean the horizon.log file
Schedule::exec(function () {
    $horizonLogFile = storage_path('logs/horizon.log');
    if (file_exists($horizonLogFile)) {
        file_put_contents($horizonLogFile, '');
    }
})->weeklyOn(1, '00:00');
