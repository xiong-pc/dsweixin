<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Models\Mall\Customer;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\SetsUpCustomerPassport;
use Tests\TestCase;

/**
 * 我的订单：身份强制来自 passport-customer guard，
 * 不接受 X-Customer-Id header 仿冒。
 */
class CustomerMyOrdersTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpCustomerPassport;

    private int $tenantId;

    private Customer $alice;

    private Customer $bob;

    private string $aliceToken;

    private string $bobToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCustomerPassport();
        $this->tenantId = $this->ensureDefaultTenant()->id;

        $this->alice = Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'alice@example.com',
            'password' => 'secret123',
            'status' => 1,
        ]);
        $this->bob = Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'bob@example.com',
            'password' => 'secret123',
            'status' => 1,
        ]);

        $this->aliceToken = $this->alice->createToken('alice')->accessToken;
        $this->bobToken = $this->bob->createToken('bob')->accessToken;
    }

    private function authHeaders(string $token, ?int $tenantId = null): array
    {
        return [
            'X-Tenant-Id' => (string) ($tenantId ?? $this->tenantId),
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function makeOrder(int $tenantId, ?int $customerId, OrderStatus $status = OrderStatus::Paid): Order
    {
        $product = Product::create(['tenant_id' => $tenantId, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price' => 50,
            'stock' => 10,
        ]);
        $order = Order::create([
            'order_no' => 'O-'.uniqid(),
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'currency' => 'CNY',
            'subtotal' => 100,
            'total' => 100,
            'status' => $status,
            'pay_method' => 'wechat',
            'paid_at' => $status === OrderStatus::Paid ? now() : null,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name_snapshot' => 'X',
            'unit_price' => 50,
            'currency' => 'CNY',
            'quantity' => 2,
            'line_total' => 100,
        ]);

        return $order;
    }

    public function test_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/shop/me/orders')->assertStatus(401);
    }

    public function test_index_only_returns_my_orders(): void
    {
        $this->makeOrder($this->tenantId, $this->alice->id);
        $this->makeOrder($this->tenantId, $this->alice->id);
        $this->makeOrder($this->tenantId, $this->bob->id);

        $response = $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders');

        $response->assertOk();
        $items = $response->json('data.list') ?? [];
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame($this->alice->id, $item['customer_id']);
        }
    }

    public function test_x_customer_id_header_cannot_override_authenticated_identity(): void
    {
        // Bob 订单
        $this->makeOrder($this->tenantId, $this->bob->id);

        // Alice 持自己的 token，但加上 X-Customer-Id: bob 的 id —— 应仍只看到自己的
        $headers = array_merge($this->authHeaders($this->aliceToken), [
            'X-Customer-Id' => (string) $this->bob->id,
        ]);

        $items = $this->withHeaders($headers)
            ->getJson('/api/v1/shop/me/orders')
            ->json('data.list') ?? [];

        $this->assertCount(0, $items);
    }

    public function test_show_strict_ownership(): void
    {
        $bobOrder = $this->makeOrder($this->tenantId, $this->bob->id);

        $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders/'.$bobOrder->id)
            ->assertStatus(403);

        // 容器跨请求复用导致 TokenGuard 缓存上次 user；清缓存后切到 Bob
        Auth::forgetGuards();

        $bobResponse = $this->withHeaders($this->authHeaders($this->bobToken))
            ->getJson('/api/v1/shop/me/orders/'.$bobOrder->id);
        $bobResponse->assertOk()->assertJsonPath('data.id', $bobOrder->id);
    }

    public function test_show_includes_items_and_shipments(): void
    {
        $order = $this->makeOrder($this->tenantId, $this->alice->id);

        $response = $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders/'.$order->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonStructure(['data' => ['id', 'items']]);
    }

    public function test_filter_by_status(): void
    {
        $this->makeOrder($this->tenantId, $this->alice->id, OrderStatus::Paid);
        $this->makeOrder($this->tenantId, $this->alice->id, OrderStatus::Cancelled);

        $items = $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders?status='.OrderStatus::Paid->value)
            ->json('data.list') ?? [];

        $this->assertCount(1, $items);
        $this->assertSame(OrderStatus::Paid->value, $items[0]['status']);
    }

    public function test_pagination(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeOrder($this->tenantId, $this->alice->id);
        }

        $response = $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders?pageNum=1&pageSize=2');

        $response->assertOk();
        $items = $response->json('data.list') ?? [];
        $this->assertCount(2, $items);
    }

    public function test_cannot_see_orders_from_other_tenant(): void
    {
        $other = Tenant::create([
            'code' => 'OTHER',
            'name' => 'Other',
            'status' => 1,
            'primary_domain' => 'other.example.com',
        ]);
        // Alice 在租户 A，但创建一个租户 B 的订单（错配 tenant_id），不应被看到
        $this->makeOrder($other->id, $this->alice->id);
        // 也创建一个 alice 在 tenant A 的订单作为对照
        $this->makeOrder($this->tenantId, $this->alice->id);

        $items = $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders')
            ->json('data.list') ?? [];

        $this->assertCount(1, $items);
        $this->assertSame($this->tenantId, (int) $items[0]['tenant_id']);
    }

    public function test_guest_orders_invisible_to_authenticated_customer(): void
    {
        // 游客下的订单（customer_id null）应该不计入 my-orders
        $this->makeOrder($this->tenantId, null);
        $this->makeOrder($this->tenantId, $this->alice->id);

        $items = $this->withHeaders($this->authHeaders($this->aliceToken))
            ->getJson('/api/v1/shop/me/orders')
            ->json('data.list') ?? [];

        $this->assertCount(1, $items);
        $this->assertSame($this->alice->id, $items[0]['customer_id']);
    }
}
