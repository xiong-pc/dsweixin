<?php

namespace Tests\Feature\Shop;

use App\Models\ExchangeRate;
use App\Models\Mall\Order;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkout 流程 = preview 只读预览 + place-order 创建订单。
 *
 * 本测试与 OrderCreateTest 互补：
 *   - OrderCreateTest 覆盖 POST /orders（向后兼容入口，同样走 OrderService.createFromCart）
 *   - 本测试聚焦 checkout 端点本身的对外契约：preview 不写库、信号正确、preview→place 数值一致
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'cf-'.uniqid(),
            'name' => 'Checkout Flow Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'sku_prefix' => 'P-CF',
            'base_currency' => 'CNY',
            'cover_image' => 'https://cdn.example.com/p-cf.jpg',
        ]);
        $this->product->translations()->create(['locale' => 'zh-CN', 'name' => '结账测试商品']);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-CF-RED',
            'price' => 50.00,
            'stock' => 20,
        ]);
    }

    private function headers(?string $sessionId = 'guest-cf', ?int $customerId = null, ?string $currency = null): array
    {
        $h = ['X-Tenant-Id' => (string) $this->tenantId];
        if ($sessionId !== null) {
            $h['X-Session-Id'] = $sessionId;
        }
        if ($customerId !== null) {
            $h['X-Customer-Id'] = (string) $customerId;
        }
        if ($currency !== null) {
            $h['X-Currency'] = $currency;
        }

        return $h;
    }

    private function addItemToCart(int $variantId, int $quantity, array $headers): void
    {
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ], $headers)->assertOk();
    }

    private function defaultAddress(): array
    {
        return [
            'country_code' => 'CN',
            'province' => '上海',
            'city' => '上海',
            'district' => '浦东',
            'street' => '世纪大道 1 号',
            'postal_code' => '200000',
            'contact_name' => '张三',
            'contact_phone' => '13800138000',
            'contact_email' => 'test@example.com',
        ];
    }

    public function test_preview_empty_cart_returns_zero_totals(): void
    {
        $response = $this->getJson('/api/v1/shop/checkout/preview', $this->headers());

        // 注意：PHP json_encode 把整数值 float（0.0）序列化为整数 0，故断言用 int 字面量
        $response->assertOk()
            ->assertJsonPath('data.item_count', 0)
            ->assertJsonPath('data.subtotal', 0)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.is_ready_to_place', false)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_preview_computes_subtotal_and_line_totals(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 3, $h);

        $response = $this->getJson('/api/v1/shop/checkout/preview', $h);

        $response->assertOk()
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.currency', 'CNY')
            ->assertJsonPath('data.items.0.sku', 'SKU-CF-RED')
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.unit_price', 50)
            ->assertJsonPath('data.items.0.line_total', 150)
            ->assertJsonPath('data.items.0.stock_ok', true)
            ->assertJsonPath('data.items.0.available_stock', 20)
            ->assertJsonPath('data.subtotal', 150)
            ->assertJsonPath('data.total', 150)
            ->assertJsonPath('data.is_ready_to_place', true);
    }

    public function test_preview_does_not_write_database(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 2, $h);

        $this->getJson('/api/v1/shop/checkout/preview', $h)->assertOk();
        $this->getJson('/api/v1/shop/checkout/preview', $h)->assertOk();

        // 预览不创建订单
        $this->assertSame(0, Order::query()->count());
        // 预览不修改库存与预占
        $fresh = $this->variant->fresh();
        $this->assertSame(20, (int) $fresh->stock);
        $this->assertSame(0, (int) $fresh->reserved);
    }

    public function test_preview_signals_insufficient_stock(): void
    {
        // 先把 reserved 拉到接近上限，模拟可用库存不足
        $this->variant->update(['reserved' => 18]); // available = 20 - 18 = 2

        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 5, $h); // 5 > 2

        $response = $this->getJson('/api/v1/shop/checkout/preview', $h);

        $response->assertOk()
            ->assertJsonPath('data.items.0.stock_ok', false)
            ->assertJsonPath('data.items.0.available_stock', 2)
            ->assertJsonPath('data.is_ready_to_place', false);
    }

    public function test_preview_applies_three_segment_pricing(): void
    {
        // markup 20% + 汇率 0.14（CNY → USD）
        Tenant::query()->where('id', $this->tenantId)->update(['price_markup_pct' => 20.0]);
        ExchangeRate::create([
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'rate' => 0.14,
            'source' => 'test',
            'fetched_at' => now(),
        ]);

        $h = $this->headers(currency: 'USD');
        $this->addItemToCart($this->variant->id, 2, $h);

        $response = $this->getJson('/api/v1/shop/checkout/preview', $h);

        // 50 CNY × 1.20 × 0.14 = 8.40 USD; 2 件 = 16.80
        $response->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.exchange_rate', 0.14)
            ->assertJsonPath('data.items.0.unit_price', 8.4)
            ->assertJsonPath('data.items.0.line_total', 16.8)
            ->assertJsonPath('data.subtotal', 16.8)
            ->assertJsonPath('data.total', 16.8);
    }

    public function test_preview_requires_identity_header(): void
    {
        // 没有 X-Customer-Id 也没有 X-Session-Id
        $response = $this->getJson('/api/v1/shop/checkout/preview', [
            'X-Tenant-Id' => (string) $this->tenantId,
        ]);

        $response->assertStatus(400);
    }

    public function test_place_order_creates_order_and_reserves_stock(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 4, $h);

        $response = $this->postJson('/api/v1/shop/checkout/place-order', [
            'shipping_address' => $this->defaultAddress(),
            'remark' => 'checkout flow',
        ], $h);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', '200.00')  // OrderResource via decimal cast → string
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.remark', 'checkout flow')
            ->assertJsonCount(1, 'data.items');

        // 库存被预占
        $this->assertSame(4, (int) $this->variant->fresh()->reserved);
        $this->assertSame(20, (int) $this->variant->fresh()->stock);

        // 购物车被清空
        $this->getJson('/api/v1/shop/cart', $h)->assertJsonPath('data.item_count', 0);

        // 数据库落了订单
        $this->assertDatabaseHas('orders', [
            'tenant_id' => $this->tenantId,
            'status' => 'pending',
            'subtotal' => 200.00,
        ]);
    }

    public function test_preview_total_equals_place_order_total(): void
    {
        Tenant::query()->where('id', $this->tenantId)->update(['price_markup_pct' => 15.0]);

        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 2, $h);

        // 1) 预览
        $previewResp = $this->getJson('/api/v1/shop/checkout/preview', $h)->assertOk();
        $previewTotal = (float) $previewResp->json('data.total');

        // 2) 真实下单
        $placeResp = $this->postJson('/api/v1/shop/checkout/place-order', [
            'shipping_address' => $this->defaultAddress(),
        ], $h)->assertOk();
        $placeTotal = (float) $placeResp->json('data.total');

        // preview 与 place 价格必须一致（spec：预览价格正确 → 下单成功 → 库存预占）
        $this->assertSame(115.00, $previewTotal);
        $this->assertSame($previewTotal, $placeTotal);
    }

    public function test_place_order_validates_shipping_address(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 1, $h);

        $address = $this->defaultAddress();
        unset($address['country_code']);

        $response = $this->postJson('/api/v1/shop/checkout/place-order', [
            'shipping_address' => $address,
        ], $h);

        $response->assertStatus(422);
    }
}
