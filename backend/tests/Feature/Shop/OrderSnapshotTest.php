<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'test', 'name' => 'Test', 'status' => 1,
            'primary_domain' => 'test.example.com',
        ]);
        $this->tenantId = $tenant->id;
    }

    private function headers(): array
    {
        return [
            'X-Tenant-Id' => (string) $this->tenantId,
            'X-Session-Id' => 'g',
        ];
    }

    private function defaultAddress(): array
    {
        return [
            'country_code' => 'CN',
            'street' => 'Some Street',
            'contact_name' => 'Test',
            'contact_phone' => '13800138000',
        ];
    }

    public function test_product_name_is_snapshotted_at_order_time(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'cover_image' => 'https://cdn.example.com/p.jpg',
        ]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '原始商品名']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'SKU-1', 'price' => 100, 'stock' => 100,
        ]);

        // 下单
        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $h)
            ->assertOk();
        $orderResp = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h);

        $orderId = $orderResp->json('data.id');

        // 商品改名
        $product->translations()->where('locale', 'zh-CN')->update(['name' => '改名后的新名']);

        // 老订单应该显示快照名
        $response = $this->getJson("/api/v1/shop/orders/{$orderId}", $h);

        $response->assertOk()
            ->assertJsonPath('data.items.0.name', '原始商品名');

        $this->assertDatabaseHas('order_items', [
            'name_snapshot' => '原始商品名',
        ]);
    }

    public function test_unit_price_is_snapshotted(): void
    {
        $product = Product::create(['tenant_id' => $this->tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '商品']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'SKU-1', 'price' => 50, 'stock' => 100,
        ]);

        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 2], $h)
            ->assertOk();
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h)
            ->assertOk();

        // SKU 涨价
        $variant->update(['price' => 999]);

        $item = OrderItem::first();
        $this->assertSame('50.00', (string) $item->unit_price);
        $this->assertSame('100.00', (string) $item->line_total);  // 50 * 2
    }

    public function test_sku_snapshot_preserved_after_variant_deleted(): void
    {
        $product = Product::create(['tenant_id' => $this->tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '商品']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'ORIGINAL-SKU', 'price' => 100, 'stock' => 50,
        ]);

        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $h)
            ->assertOk();
        $orderResp = $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h);
        $orderId = $orderResp->json('data.id');

        // 软删 variant
        $variant->delete();

        $response = $this->getJson("/api/v1/shop/orders/{$orderId}", $h);
        $response->assertOk()
            ->assertJsonPath('data.items.0.sku', 'ORIGINAL-SKU');
    }

    public function test_spec_text_snapshot_combines_spec_and_value_names(): void
    {
        // 颜色: 红 / 尺码: M
        $colorSpec = Specification::create(['tenant_id' => $this->tenantId, 'code' => 'color']);
        $colorSpec->translations()->create(['locale' => 'zh-CN', 'name' => '颜色']);
        $red = SpecificationValue::create([
            'specification_id' => $colorSpec->id, 'code' => 'red',
        ]);
        $red->translations()->create(['locale' => 'zh-CN', 'name' => '红']);

        $sizeSpec = Specification::create(['tenant_id' => $this->tenantId, 'code' => 'size']);
        $sizeSpec->translations()->create(['locale' => 'zh-CN', 'name' => '尺码']);
        $m = SpecificationValue::create(['specification_id' => $sizeSpec->id, 'code' => 'm']);
        $m->translations()->create(['locale' => 'zh-CN', 'name' => 'M']);

        $product = Product::create(['tenant_id' => $this->tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'T 恤']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'TS-RED-M', 'price' => 100, 'stock' => 10,
        ]);
        $variant->specificationValues()->sync([$red->id, $m->id]);

        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $h)
            ->assertOk();
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h)
            ->assertOk();

        $item = OrderItem::first();
        $this->assertStringContainsString('颜色: 红', $item->spec_text_snapshot);
        $this->assertStringContainsString('尺码: M', $item->spec_text_snapshot);
    }

    public function test_image_snapshot_falls_back_to_product_cover_when_variant_no_image(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'cover_image' => 'https://cdn.example.com/cover.jpg',
        ]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'P']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 50, 'stock' => 10, 'image' => '',
        ]);

        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $h);
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h);

        $item = OrderItem::first();
        $this->assertSame('https://cdn.example.com/cover.jpg', $item->image_snapshot);
    }

    public function test_image_snapshot_uses_variant_image_when_set(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'cover_image' => 'https://cdn.example.com/cover.jpg',
        ]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'P']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 50, 'stock' => 10,
            'image' => 'https://cdn.example.com/variant.jpg',
        ]);

        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $h);
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h);

        $item = OrderItem::first();
        $this->assertSame('https://cdn.example.com/variant.jpg', $item->image_snapshot);
    }

    public function test_order_currency_snapshot_independent_of_later_changes(): void
    {
        $product = Product::create(['tenant_id' => $this->tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'P']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 10,
        ]);

        $h = array_merge($this->headers(), ['X-Currency' => 'USD']);
        $this->postJson('/api/v1/shop/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $h);
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h);

        $order = Order::first();
        $this->assertSame('USD', $order->currency);
        $this->assertSame('USD', $order->items()->first()->currency);
    }
}
