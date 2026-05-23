<?php

namespace Tests\Unit\Shop;

use App\Models\Country;
use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\ShippingMethod;
use App\Models\Tenant;
use App\Models\Zone;
use App\Services\Api\Shop\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ShippingService::quote / calculate 多场景。
 *
 * 覆盖：
 *   - 重量分段命中（含 weight_max=0 无上限）
 *   - 满减免运费（free_threshold ≤ subtotal）
 *   - zone 未覆盖（country 没归属任何 zone）
 *   - country 不存在
 *   - 多 method（按 sort 排序）
 *   - 多 zone 重叠（一国属多个 zone）
 *   - 重量单位转换 g/kg/oz/lb
 *   - 空购物车 / variant 缺失
 */
class ShippingCalculateTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private Country $country;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'sh-'.uniqid(),
            'name' => 'Shipping Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        // 默认收货国 CN ∈ zone APAC
        $this->country = Country::create(['code' => 'CN', 'name' => 'China']);
        $this->zone = Zone::create(['code' => 'APAC', 'name' => 'Asia-Pacific']);
        $this->zone->countries()->attach($this->country->id);
    }

    private function makeCart(string $currency = 'CNY'): Cart
    {
        return Cart::create([
            'tenant_id' => $this->tenantId,
            'session_id' => 'sess-'.uniqid(),
            'currency' => $currency,
            'locale' => 'zh-CN',
        ]);
    }

    private function makeVariant(float $price, float $weight, string $unit = 'g'): ProductVariant
    {
        $product = Product::create(['tenant_id' => $this->tenantId, 'base_currency' => 'CNY']);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price' => $price,
            'weight' => $weight,
            'weight_unit' => $unit,
            'stock' => 100,
        ]);
    }

    private function makeMethod(string $code, string $name = 'Standard'): ShippingMethod
    {
        $method = ShippingMethod::create([
            'tenant_id' => $this->tenantId,
            'code' => $code,
            'carrier' => 'SF',
            'status' => 1,
        ]);
        $method->translations()->create(['locale' => 'zh-CN', 'name' => $name]);

        return $method;
    }

    private function addToCart(Cart $cart, ProductVariant $variant, int $qty = 1): void
    {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'quantity' => $qty,
        ]);
    }

    public function test_returns_empty_when_country_unknown(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(100, 500));
        $method = $this->makeMethod('std');
        $method->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 12]);

        $rows = app(ShippingService::class)->quote($cart, 'XX');
        $this->assertSame([], $rows);
    }

    public function test_returns_empty_when_country_in_no_zone(): void
    {
        // JP 国家但不归任何 zone
        Country::create(['code' => 'JP', 'name' => 'Japan']);

        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(100, 500));
        $method = $this->makeMethod('std');
        $method->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 12]);

        $rows = app(ShippingService::class)->quote($cart, 'JP');
        $this->assertSame([], $rows);
    }

    public function test_returns_empty_when_cart_empty(): void
    {
        $cart = $this->makeCart();
        $method = $this->makeMethod('std');
        $method->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 12]);

        $this->assertSame([], app(ShippingService::class)->quote($cart, 'CN'));
    }

    public function test_matches_first_weight_segment(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 300), 1); // 300g

        $method = $this->makeMethod('std');
        $method->rates()->createMany([
            ['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 500, 'price' => 8],
            ['zone_id' => $this->zone->id, 'weight_min' => 500, 'weight_max' => 2000, 'price' => 15],
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertCount(1, $rows);
        $this->assertSame(8.0, $rows[0]['fee']);
        $this->assertSame(300, $rows[0]['weight_g']);
    }

    public function test_matches_higher_weight_segment(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 1500), 1); // 1500g

        $method = $this->makeMethod('std');
        $method->rates()->createMany([
            ['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 500, 'price' => 8],
            ['zone_id' => $this->zone->id, 'weight_min' => 500, 'weight_max' => 2000, 'price' => 15],
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame(15.0, $rows[0]['fee']);
    }

    public function test_weight_max_zero_means_unlimited(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 50000), 1); // 50kg

        $method = $this->makeMethod('std');
        $method->rates()->createMany([
            ['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 5000, 'price' => 10],
            ['zone_id' => $this->zone->id, 'weight_min' => 5000, 'weight_max' => 0, 'price' => 99],
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame(99.0, $rows[0]['fee']);
    }

    public function test_no_rate_match_excludes_method(): void
    {
        // 重量超过最高分段且没有 unlimited 段
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 10000), 1); // 10kg

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 5000, 'price' => 10,
        ]);

        $this->assertSame([], app(ShippingService::class)->quote($cart, 'CN'));
    }

    public function test_free_threshold_makes_fee_zero(): void
    {
        $cart = $this->makeCart();
        // 4 件 × 50 = 200，超过 free_threshold=199
        $this->addToCart($cart, $this->makeVariant(50, 200), 4);

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $this->zone->id,
            'weight_min' => 0,
            'weight_max' => 0,
            'price' => 12,
            'free_threshold' => 199,
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertCount(1, $rows);
        $this->assertSame(0.0, $rows[0]['fee']);
        $this->assertTrue($rows[0]['is_free']);
    }

    public function test_free_threshold_not_met_keeps_fee(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 200), 1); // subtotal 50

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $this->zone->id,
            'weight_min' => 0,
            'weight_max' => 0,
            'price' => 12,
            'free_threshold' => 199,
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame(12.0, $rows[0]['fee']);
        $this->assertFalse($rows[0]['is_free']);
    }

    public function test_free_threshold_zero_means_no_free(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(99999, 100), 1); // 极高 subtotal

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $this->zone->id,
            'weight_min' => 0,
            'weight_max' => 0,
            'price' => 12,
            'free_threshold' => 0, // 显式 0 = 不免
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame(12.0, $rows[0]['fee']);
    }

    public function test_multiple_methods_returned_sorted_by_sort_field(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 500), 1);

        $express = $this->makeMethod('express', '加急');
        $express->update(['sort' => 1]);
        $express->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 30]);

        $std = $this->makeMethod('std', '普通');
        $std->update(['sort' => 0]);
        $std->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 10]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertCount(2, $rows);
        $this->assertSame('std', $rows[0]['code']);     // sort=0 在前
        $this->assertSame('express', $rows[1]['code']);
    }

    public function test_disabled_method_excluded(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 500), 1);

        $on = $this->makeMethod('on');
        $on->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 10]);

        $off = $this->makeMethod('off');
        $off->update(['status' => 0]);
        $off->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 5]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertCount(1, $rows);
        $this->assertSame('on', $rows[0]['code']);
    }

    public function test_weight_unit_kg_converts_to_grams(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 1.5, 'kg'), 1); // 1.5 kg = 1500 g

        $method = $this->makeMethod('std');
        $method->rates()->createMany([
            ['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 1000, 'price' => 8],
            ['zone_id' => $this->zone->id, 'weight_min' => 1000, 'weight_max' => 5000, 'price' => 20],
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame(1500, $rows[0]['weight_g']);
        $this->assertSame(20.0, $rows[0]['fee']);
    }

    public function test_weight_unit_lb_converts_to_grams(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 1.0, 'lb'), 1); // 1 lb ≈ 454 g

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 10,
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame(454, $rows[0]['weight_g']);
    }

    public function test_country_in_multiple_zones_uses_any_match(): void
    {
        // SG ∈ APAC + ASEAN，method 只配 ASEAN 也能命中
        $sg = Country::create(['code' => 'SG', 'name' => 'Singapore']);
        $asean = Zone::create(['code' => 'ASEAN', 'name' => 'ASEAN']);
        $asean->countries()->attach($sg->id);
        $apac = $this->zone; // 已存在
        $apac->countries()->attach($sg->id);

        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 500), 1);

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $asean->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 25,
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'SG');
        $this->assertCount(1, $rows);
        $this->assertSame(25.0, $rows[0]['fee']);
    }

    public function test_calculate_returns_specific_method_fee(): void
    {
        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 500), 1);

        $std = $this->makeMethod('std');
        $std->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 10]);
        $exp = $this->makeMethod('express');
        $exp->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 25]);

        $service = app(ShippingService::class);
        $this->assertSame(10.0, $service->calculate($cart, 'CN', $std->id));
        $this->assertSame(25.0, $service->calculate($cart, 'CN', $exp->id));
        $this->assertNull($service->calculate($cart, 'CN', 99999));
    }

    public function test_method_belonging_to_other_tenant_excluded(): void
    {
        $other = Tenant::create([
            'code' => 'oth-'.uniqid(),
            'name' => 'Other',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $foreign = ShippingMethod::create([
            'tenant_id' => $other->id, 'code' => 'foreign', 'status' => 1,
        ]);
        $foreign->rates()->create(['zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 5]);

        $cart = $this->makeCart();
        $this->addToCart($cart, $this->makeVariant(50, 500), 1);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertSame([], $rows);
    }

    public function test_response_shape_complete(): void
    {
        $cart = $this->makeCart('USD');
        $this->addToCart($cart, $this->makeVariant(50, 500), 2);

        $method = $this->makeMethod('std');
        $method->rates()->create([
            'zone_id' => $this->zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 12.50,
        ]);

        $rows = app(ShippingService::class)->quote($cart, 'CN');
        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertArrayHasKey('method_id', $row);
        $this->assertArrayHasKey('code', $row);
        $this->assertArrayHasKey('carrier', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('fee', $row);
        $this->assertArrayHasKey('is_free', $row);
        $this->assertArrayHasKey('weight_g', $row);
        $this->assertArrayHasKey('currency', $row);
        $this->assertArrayHasKey('rate_id', $row);
        $this->assertSame('USD', $row['currency']);
        $this->assertSame(1000, $row['weight_g']);
        $this->assertSame(12.5, $row['fee']);
    }
}
