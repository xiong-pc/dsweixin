<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Cart;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartGuestToCustomerMergeTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private ProductVariant $v1;

    private ProductVariant $v2;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'test', 'name' => 'Test', 'status' => 1,
            'primary_domain' => 'test.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $product = Product::create(['tenant_id' => $this->tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'P']);

        $this->v1 = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 50, 'stock' => 100,
        ]);
        $this->v2 = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V2', 'price' => 80, 'stock' => 100,
        ]);
    }

    public function test_merge_transfers_guest_items_to_customer(): void
    {
        // 游客加 2 个 v1
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->v1->id, 'quantity' => 2,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'guest-xyz'])->assertOk();

        // 登录后调 merge
        $response = $this->postJson('/api/v1/shop/cart/merge', [
            'customer_id' => 77,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'guest-xyz']);

        $response->assertOk()
            ->assertJsonPath('data.customer_id', 77)
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total_quantity', 2);

        // 游客 cart 被删
        $this->assertDatabaseMissing('carts', ['session_id' => 'guest-xyz']);
    }

    public function test_merge_combines_quantities_when_same_variant(): void
    {
        // 游客有 v1 数量 2
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->v1->id, 'quantity' => 2,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'guest'])->assertOk();

        // 用户已登录加 v1 数量 5
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->v1->id, 'quantity' => 5,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Customer-Id' => '7'])->assertOk();

        // 触发合并
        $response = $this->postJson('/api/v1/shop/cart/merge', [
            'customer_id' => 7,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'guest']);

        $response->assertOk()
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total_quantity', 7); // 2 + 5

        $this->assertSame(1, Cart::count());
    }

    public function test_merge_adds_guest_unique_items_to_customer(): void
    {
        // 游客有 v1
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->v1->id, 'quantity' => 1,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'g'])->assertOk();

        // 用户已有 v2
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->v2->id, 'quantity' => 3,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Customer-Id' => '7'])->assertOk();

        // 合并
        $response = $this->postJson('/api/v1/shop/cart/merge', [
            'customer_id' => 7,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'g']);

        $response->assertOk()
            ->assertJsonPath('data.item_count', 2)  // v1 + v2
            ->assertJsonPath('data.total_quantity', 4);
    }

    public function test_merge_with_no_guest_cart_returns_customer_cart(): void
    {
        // 没游客 cart，直接合并到 customer
        $response = $this->postJson('/api/v1/shop/cart/merge', [
            'customer_id' => 7,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'no-such-session']);

        $response->assertOk()
            ->assertJsonPath('data.customer_id', 7)
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_merge_requires_both_customer_id_and_session_id(): void
    {
        $response = $this->postJson('/api/v1/shop/cart/merge', [], [
            'X-Tenant-Id' => (string) $this->tenantId,
            'X-Session-Id' => 'g',
        ]);

        $response->assertStatus(400);
    }

    public function test_merge_with_same_locale_preserved(): void
    {
        // 游客 cart 用 en-US
        $this->getJson('/api/v1/shop/cart', [
            'X-Tenant-Id' => (string) $this->tenantId,
            'X-Session-Id' => 'g',
            'X-Locale' => 'en-US',
            'X-Currency' => 'USD',
        ])->assertOk();

        // 合并到 customer（无 customer cart 先存在）
        $response = $this->postJson('/api/v1/shop/cart/merge', [
            'customer_id' => 7,
        ], ['X-Tenant-Id' => (string) $this->tenantId, 'X-Session-Id' => 'g']);

        $response->assertOk()
            ->assertJsonPath('data.locale', 'en-US')
            ->assertJsonPath('data.currency', 'USD');
    }
}
