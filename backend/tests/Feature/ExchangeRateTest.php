<?php

namespace Tests\Feature;

use App\Jobs\SyncExchangeRatesJob;
use App\Models\ExchangeRate;
use App\Services\Api\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private function createRate(string $from, string $to, float $rate): ExchangeRate
    {
        return ExchangeRate::create([
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'source' => 'manual',
            'fetched_at' => now(),
        ]);
    }

    public function test_admin_can_list_exchange_rates(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $this->createRate('CNY', 'USD', 0.1389);
        $this->createRate('CNY', 'EUR', 0.1268);

        $response = $this->getJson('/api/v1/system/exchange-rates');

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_super_admin_can_create_exchange_rate(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => 0.1389,
            'source' => 'manual',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.from_currency', 'CNY')
            ->assertJsonPath('data.to_currency', 'USD');

        $this->assertDatabaseHas('exchange_rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
        ]);
    }

    public function test_admin_cannot_create_exchange_rate(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => 0.1389,
        ]);

        $response->assertStatus(403);
    }

    public function test_from_and_to_currency_must_differ(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'CNY',
            'rate' => 1.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_rate_must_be_positive(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => -1,
        ]);

        $response->assertStatus(422);
    }

    public function test_currency_pair_uniqueness_via_upsert(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/system/exchange-rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => 0.14,
        ])->assertOk();

        // 重复对会 upsert（更新而非冲突）
        $r2 = $this->postJson('/api/v1/system/exchange-rates', [
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => 0.1389,
        ]);

        $r2->assertOk();
        $this->assertSame(1, ExchangeRate::where('from_currency', 'CNY')->where('to_currency', 'USD')->count());
        $this->assertEquals(0.13890000, ExchangeRate::where('from_currency', 'CNY')->where('to_currency', 'USD')->first()->rate);
    }

    public function test_super_admin_can_update_rate(): void
    {
        $this->actingAsSuperAdmin();
        $rate = $this->createRate('CNY', 'JPY', 20.5);

        $response = $this->putJson("/api/v1/system/exchange-rates/{$rate->id}", [
            'rate' => 20.85,
            'source' => 'exchangerate-api',
        ]);

        $response->assertOk();
        $this->assertEqualsWithDelta(20.85, (float) $rate->fresh()->rate, 0.0001);
    }

    public function test_super_admin_can_delete_rate(): void
    {
        $this->actingAsSuperAdmin();
        $rate = $this->createRate('CNY', 'KRW', 190.5);

        $response = $this->deleteJson("/api/v1/system/exchange-rates/{$rate->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('exchange_rates', ['id' => $rate->id]);
    }

    public function test_sync_endpoint_dispatches_job(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates/sync', [
            'base_currency' => 'CNY',
            'source' => 'mock-provider',
            'rates' => [
                'USD' => 0.1389,
                'EUR' => 0.1268,
                'JPY' => 20.85,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.synced', 3);

        $this->assertDatabaseHas('exchange_rates', ['from_currency' => 'CNY', 'to_currency' => 'USD', 'source' => 'mock-provider']);
        $this->assertDatabaseHas('exchange_rates', ['from_currency' => 'CNY', 'to_currency' => 'JPY']);
    }

    public function test_admin_cannot_trigger_sync(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates/sync', [
            'rates' => ['USD' => 0.14],
        ]);

        $response->assertStatus(403);
    }

    public function test_sync_requires_rates(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/exchange-rates/sync', []);

        $response->assertStatus(422);
    }

    public function test_sync_job_handles_rates_directly(): void
    {
        // 单测：Job 不通过 HTTP 调用也能正常 upsert
        $service = app(ExchangeRateService::class);
        $job = new SyncExchangeRatesJob('USD', ['CNY' => 7.2, 'EUR' => 0.91], 'test');
        $job->handle($service);

        $this->assertDatabaseHas('exchange_rates', ['from_currency' => 'USD', 'to_currency' => 'CNY', 'source' => 'test']);
        $this->assertDatabaseHas('exchange_rates', ['from_currency' => 'USD', 'to_currency' => 'EUR']);
    }

    public function test_sync_job_skips_invalid_rates(): void
    {
        $service = app(ExchangeRateService::class);
        $job = new SyncExchangeRatesJob('CNY', ['USD' => -1, 'EUR' => 0, 'JPY' => 'bad', 'KRW' => 190.5], 'test');
        $job->handle($service);

        $this->assertDatabaseMissing('exchange_rates', ['to_currency' => 'USD']);
        $this->assertDatabaseMissing('exchange_rates', ['to_currency' => 'EUR']);
        $this->assertDatabaseMissing('exchange_rates', ['to_currency' => 'JPY']);
        $this->assertDatabaseHas('exchange_rates', ['from_currency' => 'CNY', 'to_currency' => 'KRW']);
    }

    public function test_convert_static_method(): void
    {
        $this->createRate('CNY', 'USD', 0.1389);

        $this->assertSame(100.0, ExchangeRate::convert('CNY', 'CNY', 100.0));
        $this->assertEqualsWithDelta(13.89, ExchangeRate::convert('CNY', 'USD', 100.0), 0.001);
        $this->assertNull(ExchangeRate::convert('CNY', 'XYZ', 100.0));
    }

    public function test_unauthenticated_cannot_access_rates(): void
    {
        $this->getJson('/api/v1/system/exchange-rates')->assertStatus(401);
        $this->postJson('/api/v1/system/exchange-rates/sync', [])->assertStatus(401);
    }
}
