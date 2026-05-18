<?php

namespace Tests\Feature\Shop;

use App\Models\ExchangeRate;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Shop\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PriceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = app(PriceCalculator::class);
    }

    private function makeTenant(float $markupPct = 0.0): Tenant
    {
        return Tenant::create([
            'code' => 'tt-'.uniqid(),
            'name' => 'T',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
            'price_markup_pct' => $markupPct,
        ]);
    }

    public function test_same_currency_no_markup_returns_base_price(): void
    {
        $price = $this->calc->compute(99.99, 'CNY', 'CNY', 0.0);

        $this->assertSame(99.99, $price);
    }

    public function test_markup_applies_percentage_uplift(): void
    {
        // 100 × 1.10 = 110
        $price = $this->calc->compute(100.0, 'CNY', 'CNY', 10.0);

        $this->assertSame(110.0, $price);
    }

    public function test_markup_with_decimal_percentage(): void
    {
        // 100 × 1.125 = 112.5 → 112.50
        $price = $this->calc->compute(100.0, 'CNY', 'CNY', 12.5);

        $this->assertSame(112.5, $price);
    }

    public function test_negative_markup_treated_as_zero(): void
    {
        $price = $this->calc->compute(100.0, 'CNY', 'CNY', -20.0);

        $this->assertSame(100.0, $price);
    }

    public function test_zero_or_negative_base_price_returns_zero(): void
    {
        $this->assertSame(0.0, $this->calc->compute(0.0, 'CNY', 'CNY', 10.0));
        $this->assertSame(0.0, $this->calc->compute(-1.0, 'CNY', 'CNY', 10.0));
    }

    public function test_currency_conversion_uses_exchange_rate(): void
    {
        ExchangeRate::create([
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => 0.14,
            'source' => 'test',
            'fetched_at' => now(),
        ]);

        // 100 CNY × 0.14 = 14 USD
        $price = $this->calc->compute(100.0, 'CNY', 'USD', 0.0);

        $this->assertSame(14.0, $price);
    }

    public function test_missing_exchange_rate_falls_back_to_one(): void
    {
        // 没有任何 ExchangeRate 记录
        $price = $this->calc->compute(100.0, 'CNY', 'JPY', 0.0);

        $this->assertSame(100.0, $price);
    }

    public function test_updated_exchange_rate_takes_effect(): void
    {
        // exchange_rates 表 (from, to) 有唯一约束，每对币种只存最新一行
        $rate = ExchangeRate::create([
            'from_currency' => 'CNY', 'to_currency' => 'USD',
            'rate' => 0.10, 'source' => 'old', 'fetched_at' => now()->subDays(2),
        ]);
        // 第二天同步覆盖
        $rate->update(['rate' => 0.15, 'source' => 'new', 'fetched_at' => now()]);

        $price = $this->calc->compute(100.0, 'CNY', 'USD', 0.0);

        $this->assertSame(15.0, $price);
    }

    public function test_three_segment_calculation_combines_all(): void
    {
        ExchangeRate::create([
            'from_currency' => 'CNY', 'to_currency' => 'USD',
            'rate' => 0.14, 'source' => 't', 'fetched_at' => now(),
        ]);

        // 100 CNY × 1.20 (markup 20%) × 0.14 (rate) = 16.80 USD
        $price = $this->calc->compute(100.0, 'CNY', 'USD', 20.0);

        $this->assertSame(16.8, $price);
    }

    public function test_result_rounds_to_two_decimals(): void
    {
        // 33.33 × 1.0333 = 34.4399... → 34.44
        $price = $this->calc->compute(33.33, 'CNY', 'CNY', 3.33);

        $this->assertSame(34.44, $price);
    }

    public function test_compute_for_variant_uses_product_base_currency(): void
    {
        $tenant = $this->makeTenant();
        $product = Product::create([
            'tenant_id' => $tenant->id, 'base_currency' => 'CNY', 'base_price' => 100,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 80, 'stock' => 10,
        ]);

        $price = $this->calc->computeForVariant($variant, 'CNY', $tenant->id);

        $this->assertSame(80.0, $price);
    }

    public function test_compute_for_variant_uses_tenant_markup(): void
    {
        $tenant = $this->makeTenant(15.0);
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        // 100 × 1.15 = 115
        $price = $this->calc->computeForVariant($variant, 'CNY', $tenant->id);

        $this->assertSame(115.0, $price);
    }

    public function test_compute_for_variant_full_three_segment(): void
    {
        ExchangeRate::create([
            'from_currency' => 'CNY', 'to_currency' => 'USD',
            'rate' => 0.14, 'source' => 't', 'fetched_at' => now(),
        ]);

        $tenant = $this->makeTenant(20.0);
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        // 100 × 1.20 × 0.14 = 16.80 USD
        $price = $this->calc->computeForVariant($variant, 'USD', $tenant->id);

        $this->assertSame(16.8, $price);
    }

    public function test_compute_for_variant_target_defaults_to_base_currency(): void
    {
        $tenant = $this->makeTenant(10.0);
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'EUR']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 50, 'stock' => 10,
        ]);

        // null target => 用 base = EUR
        $price = $this->calc->computeForVariant($variant, null, $tenant->id);

        $this->assertSame(55.0, $price);
    }

    public function test_compute_for_variant_without_tenant_no_markup(): void
    {
        $product = Product::create(['tenant_id' => 999, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        $price = $this->calc->computeForVariant($variant, 'CNY', null);

        $this->assertSame(100.0, $price);
    }

    public function test_resolve_markup_returns_zero_for_unknown_tenant(): void
    {
        $this->assertSame(0.0, $this->calc->resolveMarkup(99999));
        $this->assertSame(0.0, $this->calc->resolveMarkup(null));
        $this->assertSame(0.0, $this->calc->resolveMarkup(0));
    }

    public function test_resolve_exchange_rate_same_currency_is_one(): void
    {
        $this->assertSame(1.0, $this->calc->resolveExchangeRate('CNY', 'CNY'));
        $this->assertSame(1.0, $this->calc->resolveExchangeRate('USD', 'USD'));
    }
}
