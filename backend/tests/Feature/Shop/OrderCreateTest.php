<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Mall\Order;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'test', 'name' => 'Test', 'status' => 1,
            'primary_domain' => 'test.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'sku_prefix' => 'P-001',
            'cover_image' => 'https://cdn.example.com/p.jpg',
        ]);
        $this->product->translations()->create(['locale' => 'zh-CN', 'name' => '测试商品']);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-RED-M',
            'price' => 99.99,
            'stock' => 100,
        ]);
    }

    private function headers(?string $sessionId = 'guest-1', ?int $customerId = null): array
    {
        $h = ['X-Tenant-Id' => (string) $this->tenantId];
        if ($sessionId !== null) {
            $h['X-Session-Id'] = $sessionId;
        }
        if ($customerId !== null) {
            $h['X-Customer-Id'] = (string) $customerId;
        }

        return $h;
    }

    private function addItemToCart(int $variantId, int $quantity, array $headers): void
    {
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $variantId, 'quantity' => $quantity,
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

    public function test_create_order_from_cart(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 2, $h);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
            'remark' => 'P0 测试单',
        ], $h);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', '199.98')
            ->assertJsonPath('data.total', '199.98')
            ->assertJsonPath('data.remark', 'P0 测试单')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $this->tenantId,
            'status' => 'pending',
            'subtotal' => 199.98,
        ]);
    }

    public function test_create_order_clears_cart(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 1, $h);

        $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h)->assertOk();

        // 购物车应被清空
        $response = $this->getJson('/api/v1/shop/cart', $h);
        $response->assertJsonPath('data.item_count', 0);
    }

    public function test_create_order_generates_unique_order_no(): void
    {
        $h1 = $this->headers(sessionId: 'g1');
        $h2 = $this->headers(sessionId: 'g2');
        $this->addItemToCart($this->variant->id, 1, $h1);
        $this->addItemToCart($this->variant->id, 1, $h2);

        $r1 = $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h1)
            ->assertOk();
        $r2 = $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h2)
            ->assertOk();

        $no1 = $r1->json('data.order_no');
        $no2 = $r2->json('data.order_no');

        $this->assertNotSame($no1, $no2);
        $this->assertStringStartsWith('O', $no1);
        $this->assertSame(21, strlen($no1)); // O + 14位时间 + 6位随机
    }

    public function test_empty_cart_cannot_create_order(): void
    {
        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $this->headers());

        $response->assertStatus(400);
    }

    public function test_create_order_persists_shipping_address(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 1, $h);

        $address = $this->defaultAddress();
        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $address,
        ], $h);

        $response->assertOk()
            ->assertJsonPath('data.shipping_address.country_code', 'CN')
            ->assertJsonPath('data.shipping_address.contact_name', '张三')
            ->assertJsonPath('data.shipping_address.street', '世纪大道 1 号');

        $this->assertDatabaseHas('order_addresses', [
            'type' => 'shipping',
            'country_code' => 'CN',
            'contact_name' => '张三',
        ]);
    }

    public function test_billing_address_optional(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 1, $h);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
            'billing_address' => array_merge($this->defaultAddress(), ['contact_name' => '李四']),
        ], $h);

        $response->assertOk();
        $this->assertDatabaseHas('order_addresses', [
            'type' => 'billing', 'contact_name' => '李四',
        ]);
        $this->assertDatabaseHas('order_addresses', [
            'type' => 'shipping', 'contact_name' => '张三',
        ]);
    }

    public function test_address_validation_country_required(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 1, $h);

        $address = $this->defaultAddress();
        unset($address['country_code']);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $address,
        ], $h);

        $response->assertStatus(422);
    }

    public function test_logged_in_user_creates_order_under_customer_id(): void
    {
        $h = $this->headers(sessionId: null, customerId: 42);
        $this->addItemToCart($this->variant->id, 3, $h);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h);

        $response->assertOk()->assertJsonPath('data.customer_id', 42);
        $this->assertDatabaseHas('orders', ['customer_id' => 42]);
    }

    public function test_list_only_returns_orders_for_current_identity(): void
    {
        $h1 = $this->headers(sessionId: 'g1');
        $h2 = $this->headers(sessionId: 'g2');

        $this->addItemToCart($this->variant->id, 1, $h1);
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h1)->assertOk();

        $this->addItemToCart($this->variant->id, 1, $h2);
        $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h2)->assertOk();

        $response = $this->getJson('/api/v1/shop/orders', $h1);
        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
    }

    public function test_cannot_access_other_users_order(): void
    {
        $h1 = $this->headers(sessionId: 'g1');
        $this->addItemToCart($this->variant->id, 1, $h1);
        $orderResp = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h1)->assertOk();
        $orderId = $orderResp->json('data.id');

        $h2 = $this->headers(sessionId: 'g2');
        $response = $this->getJson("/api/v1/shop/orders/{$orderId}", $h2);

        $response->assertStatus(403);
    }

    public function test_order_default_status_is_pending(): void
    {
        $h = $this->headers();
        $this->addItemToCart($this->variant->id, 1, $h);

        $resp = $this->postJson('/api/v1/shop/orders', ['shipping_address' => $this->defaultAddress()], $h);
        $resp->assertJsonPath('data.status', OrderStatus::Pending->value);

        $order = Order::first();
        $this->assertSame(OrderStatus::Pending, $order->status);
    }

    public function test_order_inherits_cart_currency(): void
    {
        $h = array_merge($this->headers(), ['X-Currency' => 'USD']);
        $this->addItemToCart($this->variant->id, 1, $h);

        $resp = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h);

        $resp->assertOk()->assertJsonPath('data.currency', 'USD');
    }
}
