<?php

namespace Tests\Feature\Shop;

use App\Models\ExchangeRate;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPriceIntegrationTest extends TestCase
{
    use RefreshDatabase;

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

    private function defaultAddress(): array
    {
        return [
            'country_code' => 'CN', 'street' => 'X',
            'contact_name' => 'T', 'contact_phone' => '13800138000',
        ];
    }

    private function placeOrder(int $tenantId, int $variantId, int $qty, ?string $currency = null): array
    {
        $headers = ['X-Tenant-Id' => (string) $tenantId, 'X-Session-Id' => 'g'];
        if ($currency !== null) {
            $headers['X-Currency'] = $currency;
        }

        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $variantId, 'quantity' => $qty,
        ], $headers)->assertOk();

        return $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $headers)->assertOk()->json();
    }

    public function test_order_unit_price_equals_variant_price_when_no_markup_same_currency(): void
    {
        $tenant = $this->makeTenant(0.0);
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 99.99, 'stock' => 10,
        ]);

        $resp = $this->placeOrder($tenant->id, $variant->id, 2);

        $this->assertSame('99.99', $resp['data']['items'][0]['unit_price']);
        $this->assertSame('199.98', $resp['data']['subtotal']);
    }

    public function test_order_unit_price_includes_tenant_markup(): void
    {
        $tenant = $this->makeTenant(20.0);  // 20% 加价
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        $resp = $this->placeOrder($tenant->id, $variant->id, 1);

        // 100 × 1.20 = 120.00
        $this->assertSame('120.00', $resp['data']['items'][0]['unit_price']);
        $this->assertSame('120.00', $resp['data']['total']);
    }

    public function test_order_unit_price_converts_currency(): void
    {
        ExchangeRate::create([
            'from_currency' => 'CNY', 'to_currency' => 'USD',
            'rate' => 0.14, 'source' => 't', 'fetched_at' => now(),
        ]);

        $tenant = $this->makeTenant(0.0);
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        $resp = $this->placeOrder($tenant->id, $variant->id, 1, 'USD');

        // 100 CNY × 0.14 = 14 USD
        $this->assertSame('14.00', $resp['data']['items'][0]['unit_price']);
        $this->assertSame('USD', $resp['data']['currency']);
    }

    public function test_order_unit_price_full_three_segment(): void
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

        $resp = $this->placeOrder($tenant->id, $variant->id, 3, 'USD');

        // 100 × 1.20 × 0.14 = 16.80 USD; 3 件 = 50.40
        $this->assertSame('16.80', $resp['data']['items'][0]['unit_price']);
        $this->assertSame('50.40', $resp['data']['subtotal']);
    }

    public function test_order_exchange_rate_field_snapshots_current_rate(): void
    {
        ExchangeRate::create([
            'from_currency' => 'CNY', 'to_currency' => 'USD',
            'rate' => 0.1357, 'source' => 't', 'fetched_at' => now(),
        ]);

        $tenant = $this->makeTenant();
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 50, 'stock' => 10,
        ]);

        $this->placeOrder($tenant->id, $variant->id, 1, 'USD');

        $order = Order::first();
        $this->assertEqualsWithDelta(0.1357, (float) $order->exchange_rate, 0.0001);
    }

    public function test_order_price_unaffected_by_later_markup_change(): void
    {
        $tenant = $this->makeTenant(10.0);
        $product = Product::create(['tenant_id' => $tenant->id, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        $this->placeOrder($tenant->id, $variant->id, 1);

        // 老订单 unit_price = 110
        $item = OrderItem::first();
        $this->assertSame('110.00', (string) $item->unit_price);

        // 后续租户改 markup
        $tenant->update(['price_markup_pct' => 50.0]);

        // 老订单不变
        $this->assertSame('110.00', (string) $item->fresh()->unit_price);
    }
}
