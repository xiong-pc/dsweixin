<?php

namespace App\Jobs;

use App\Services\Api\ExchangeRateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncExchangeRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $baseCurrency = 'CNY';

    public string $source = 'manual';

    /**
     * @param  array<string, float>  $rates  外部数据源返回的 {to_currency: rate}
     */
    public array $rates = [];

    public function __construct(string $baseCurrency = 'CNY', array $rates = [], string $source = 'manual')
    {
        $this->baseCurrency = strtoupper($baseCurrency);
        $this->rates = $rates;
        $this->source = $source;
    }

    public function handle(ExchangeRateService $service): void
    {
        if (empty($this->rates)) {
            Log::info("SyncExchangeRatesJob: no rates supplied for base {$this->baseCurrency}, skipping.");

            return;
        }

        $count = 0;
        foreach ($this->rates as $toCurrency => $rate) {
            if (! is_numeric($rate) || $rate <= 0) {
                continue;
            }

            $service->upsert([
                'from_currency' => $this->baseCurrency,
                'to_currency' => $toCurrency,
                'rate' => (float) $rate,
                'source' => $this->source,
                'fetched_at' => now(),
            ]);
            $count++;
        }

        Log::info("SyncExchangeRatesJob: synced {$count} rates from {$this->baseCurrency} (source: {$this->source})");
    }
}
