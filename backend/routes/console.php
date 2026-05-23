<?php

use App\Jobs\Mall\ReleaseExpiredOrderReservationsJob;
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

Artisan::command('mall:release-expired-reservations {--minutes=30}', function () {
    $minutes = (int) $this->option('minutes');
    if ($minutes < 1) {
        $minutes = 30;
    }

    ReleaseExpiredOrderReservationsJob::dispatchSync($minutes);
    $this->info("Released expired reservations older than {$minutes} minutes.");

    return self::SUCCESS;
})->purpose('Cancel pending orders older than N minutes and release their reserved stock');

Schedule::command('mall:release-expired-reservations')->everyMinute()->withoutOverlapping();
