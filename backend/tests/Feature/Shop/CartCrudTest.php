<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'test',
            'name' => 'Test',
            'status' => 1,
            'primary_domain' => 'test.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'sku_prefix' => 'P-001',
            'base_price' => 99,
        ]);
        $this->product->translations()->create(['locale' => 'zh-CN', 'name' => '测试商品']);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SKU-001',
            'price' => 99.99,
            'stock' => 100,
        ]);
    }

    private function headers(?int $customerId = null, ?string $sessionId = null): array
    {
        $h = ['X-Tenant-Id' => (string) $this->tenantId];
        if ($customerId !== null) {
            $h['X-Customer-Id'] = (string) $customerId;
        }
        if ($sessionId !== null) {
            $h['X-Session-Id'] = $sessionId;
        }

        return $h;
    }

    public function test_guest_can_view_empty_cart(): void
    {
        $response = $this->getJson('/api/v1/shop/cart', $this->headers(sessionId: 'guest-abc'));

        $response->assertOk()
            ->assertJsonPath('data.session_id', 'guest-abc')
            ->assertJsonPath('data.customer_id', null)
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_customer_can_view_empty_cart(): void
    {
        $response = $this->getJson('/api/v1/shop/cart', $this->headers(customerId: 42));

        $response->assertOk()
            ->assertJsonPath('data.customer_id', 42)
            ->assertJsonPath('data.session_id', '')
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_cart_requires_identity(): void
    {
        $response = $this->getJson('/api/v1/shop/cart', ['X-Tenant-Id' => (string) $this->tenantId]);

        $response->assertStatus(400);
    }

    public function test_cart_requires_tenant_header(): void
    {
        $response = $this->getJson('/api/v1/shop/cart', ['X-Session-Id' => 'g']);

        $response->assertStatus(400);
    }

    public function test_guest_can_add_variant_to_cart(): void
    {
        $response = $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 2,
        ], $this->headers(sessionId: 'g1'));

        $response->assertOk()
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total_quantity', 2);

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_variant_increases_quantity(): void
    {
        $headers = $this->headers(sessionId: 'g1');

        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 2,
        ], $headers)->assertOk();

        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 3,
        ], $headers)->assertOk();

        $response = $this->getJson('/api/v1/shop/cart', $headers);
        $response->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total_quantity', 5);
    }

    public function test_adding_invalid_variant_returns_error(): void
    {
        $response = $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => 99999,
            'quantity' => 1,
        ], $this->headers(sessionId: 'g'));

        $response->assertStatus(400);
    }

    public function test_quantity_must_be_at_least_1(): void
    {
        $response = $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 0,
        ], $this->headers(sessionId: 'g'));

        $response->assertStatus(422);
    }

    public function test_update_item_quantity(): void
    {
        $headers = $this->headers(sessionId: 'g');
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 2,
        ], $headers)->assertOk();

        $item = CartItem::first();

        $response = $this->putJson("/api/v1/shop/cart/items/{$item->id}", [
            'quantity' => 5,
        ], $headers);

        $response->assertOk();
        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_remove_item_from_cart(): void
    {
        $headers = $this->headers(sessionId: 'g');
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 1,
        ], $headers)->assertOk();

        $item = CartItem::first();

        $response = $this->deleteJson("/api/v1/shop/cart/items/{$item->id}", [], $headers);

        $response->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_clear_empties_cart(): void
    {
        $headers = $this->headers(sessionId: 'g');
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 1,
        ], $headers)->assertOk();

        $response = $this->deleteJson('/api/v1/shop/cart', [], $headers);
        $response->assertOk();

        $cart = Cart::first();
        $this->assertSame(0, $cart->items()->count());
    }

    public function test_guest_cart_and_customer_cart_are_separate(): void
    {
        // 游客
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 2,
        ], $this->headers(sessionId: 'guest1'))->assertOk();

        // 登录用户
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 5,
        ], $this->headers(customerId: 99))->assertOk();

        $this->assertSame(2, Cart::count());
    }

    public function test_cannot_modify_other_users_cart_item(): void
    {
        // 游客 1 加 item
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 1,
        ], $this->headers(sessionId: 'guest1'))->assertOk();

        $item = CartItem::first();

        // 游客 2 试图改它
        $response = $this->putJson("/api/v1/shop/cart/items/{$item->id}", [
            'quantity' => 99,
        ], $this->headers(sessionId: 'guest2'));

        $response->assertStatus(403);
    }

    public function test_cannot_modify_cross_tenant_cart_item(): void
    {
        // 在当前租户加 item
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 1,
        ], $this->headers(sessionId: 'guest1'))->assertOk();

        $item = CartItem::first();

        // 另一个租户的请求试图操作
        $response = $this->putJson("/api/v1/shop/cart/items/{$item->id}", [
            'quantity' => 99,
        ], ['X-Tenant-Id' => (string) ($this->tenantId + 999), 'X-Session-Id' => 'guest1']);

        $response->assertStatus(403);
    }

    public function test_locale_and_currency_persisted_on_cart(): void
    {
        $response = $this->getJson('/api/v1/shop/cart', array_merge(
            $this->headers(sessionId: 'g'),
            ['X-Locale' => 'en-US', 'X-Currency' => 'USD']
        ));

        $response->assertOk()
            ->assertJsonPath('data.locale', 'en-US')
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_cannot_add_variant_from_other_tenant(): void
    {
        $otherTenant = Tenant::create([
            'code' => 'other', 'name' => 'Other', 'status' => 1,
            'primary_domain' => 'other.example.com',
        ]);
        $otherProduct = Product::create(['tenant_id' => $otherTenant->id]);
        $otherVariant = ProductVariant::create([
            'product_id' => $otherProduct->id, 'sku' => 'OTHER-SKU', 'price' => 50,
        ]);

        $response = $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $otherVariant->id,
            'quantity' => 1,
        ], $this->headers(sessionId: 'g'));

        $response->assertStatus(400);
    }
}
