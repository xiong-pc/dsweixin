<?php

namespace Tests\Feature\Mall;

use App\Enums\OrderStatus;
use App\Models\Mall\Order;
use App\Models\Mall\OrderHistory;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin `POST /api/v1/mall/orders/{order}/cancel` 行为。
 *
 * 走 OrderService::cancelOrder：状态机转 Cancelled + 释放预占库存。
 *
 * 重点：
 *   - 仅 Pending / Paid 可取消（OrderStatus 状态机限制）
 *   - reason 透传到 OrderHistory（OrderObserver 静态 context 表）
 *   - tenant 隔离 / 鉴权
 *   - 库存释放（reserved -= qty）
 */
class OrderCancelActionTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $code): Tenant
    {
        return Tenant::create([
            'code' => $code,
            'name' => strtoupper($code),
            'status' => 1,
            'primary_domain' => "{$code}.example.com",
        ]);
    }

    /**
     * @return array{0: Order, 1: ProductVariant}
     */
    private function makePendingOrder(int $tenantId): array
    {
        $product = Product::create(['tenant_id' => $tenantId, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price' => 50,
            'stock' => 10,
            'reserved' => 2, // 已预占
        ]);
        $order = Order::create([
            'order_no' => 'O-'.uniqid(),
            'tenant_id' => $tenantId,
            'currency' => 'CNY',
            'subtotal' => 100,
            'total' => 100,
            'status' => OrderStatus::Pending,
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

        return [$order, $variant];
    }

    public function test_admin_can_cancel_pending_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order, $variant] = $this->makePendingOrder($tenantId);

        $response = $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [
            'reason' => 'merchant_decision',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);

        // 预占库存释放：reserved 2 → 0
        $this->assertSame(0, (int) $variant->fresh()->reserved);
        // stock 不变
        $this->assertSame(10, (int) $variant->fresh()->stock);
    }

    public function test_admin_can_cancel_paid_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePendingOrder($tenantId);
        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [])
            ->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_cannot_cancel_shipped_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePendingOrder($tenantId);
        $order->update(['status' => OrderStatus::Shipped, 'shipped_at' => now()]);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [])
            ->assertStatus(400);

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_cannot_cancel_already_cancelled_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePendingOrder($tenantId);
        $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [])
            ->assertStatus(400);
    }

    public function test_cancel_reason_propagates_to_history(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->user()->id;
        [$order] = $this->makePendingOrder($tenantId);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [
            'reason' => 'out_of_stock',
        ])->assertOk();

        $row = OrderHistory::where('order_id', $order->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->from_status);
        $this->assertSame('cancelled', $row->to_status);
        $this->assertSame('out_of_stock', $row->reason);
        $this->assertSame('user', $row->operator_type);
        $this->assertSame($userId, (int) $row->operator_id);
    }

    public function test_admin_cannot_cancel_other_tenant_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('oth');
        [$order] = $this->makePendingOrder($other->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [])
            ->assertStatus(403);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_unauthenticated_cannot_cancel(): void
    {
        $tenant = $this->createTenant('an');
        [$order] = $this->makePendingOrder($tenant->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [])
            ->assertStatus(401);
    }

    public function test_super_admin_can_cancel_any_tenant_order(): void
    {
        $this->actingAsSuperAdmin();

        $tenant = $this->createTenant('any');
        [$order] = $this->makePendingOrder($tenant->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/cancel", [])
            ->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_index_lists_own_tenant_orders_with_filters(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('oth');

        [$o1] = $this->makePendingOrder($tenantId);
        [$o2] = $this->makePendingOrder($tenantId);
        $o2->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        [$foreign] = $this->makePendingOrder($other->id);

        // 默认仅自己租户
        $list = $this->getJson('/api/v1/mall/orders');
        $list->assertOk();
        $this->assertSame(2, $list->json('data.total'));

        // status 过滤
        $paid = $this->getJson('/api/v1/mall/orders?status=paid');
        $this->assertSame(1, $paid->json('data.total'));
        $this->assertSame($o2->order_no, $paid->json('data.list.0.order_no'));
    }

    public function test_show_returns_order_with_relations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePendingOrder($tenantId);

        $response = $this->getJson("/api/v1/mall/orders/{$order->id}");
        $response->assertOk()
            ->assertJsonPath('data.order_no', $order->order_no)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(1, 'data.items');
    }
}
