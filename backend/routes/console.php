<?php

use App\Jobs\SyncExchangeRatesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mall:sync-exchange-rates {--base=CNY} {--source=manual}', function () {
    $base = strtoupper((string) $this->option('base'));
    $source = (string) $this->option('source');

    // P0：占位实现，留作 P1 接入 exchangerate-api / openexchangerates 等数据源
    $rates = [];

    if (empty($rates)) {
        $this->warn("No rates configured for base {$base}. Connect an external provider in P1.");

        return self::SUCCESS;
    }

    SyncExchangeRatesJob::dispatchSync($base, $rates, $source);
    $this->info('Exchange rates synced.');

    return self::SUCCESS;
})->purpose('Sync exchange rates from external provider (P0 placeholder)');

Schedule::command('mall:sync-exchange-rates')->everySixHours()->withoutOverlapping();
