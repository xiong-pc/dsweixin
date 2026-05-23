<?php

namespace Tests\Feature\Mall;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderShipmentStatus;
use App\Enums\OrderStatus;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\OrderShipment;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin 发货 / 物流跟踪 + 客户端查询 shipment。
 *
 * 覆盖：
 *   - ship()：Paid → Shipped + 写流水 + 同步老字段（shipping_no/company）
 *   - 拆单（Shipped 状态再发一单）
 *   - markDelivered：单 shipment Delivered；全部 Delivered → Order Delivered
 *   - cancel：撤销发货
 *   - 校验：Pending 订单不能发；缺承运商/单号；Delivered 不能再 deliver
 *   - 多租户隔离（admin 不能看其他租户）
 *   - 客户能从 Shop OrderController 看到 shipments
 */
class OrderShipmentTest extends TestCase
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

    private function makePaidOrder(int $tenantId, ?string $sessionId = null): Order
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
            'session_id' => $sessionId ?: 'sess-'.uniqid(),
            'currency' => 'CNY',
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => OrderStatus::Paid,
            'pay_method' => 'wechat',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name_snapshot' => 'Test',
            'unit_price' => 50,
            'currency' => 'CNY',
            'quantity' => 2,
            'line_total' => 100,
        ]);
        OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'wechat',
            'transaction_id' => 'TX-'.uniqid(),
            'amount' => 100.00,
            'currency' => 'CNY',
            'status' => OrderPaymentStatus::Success,
            'paid_at' => now(),
        ]);

        return $order;
    }

    public function test_admin_can_ship_paid_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        $response = $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id,
            'carrier' => 'SF',
            'tracking_no' => 'SF1234567890',
            'fee' => 12.50,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.carrier', 'SF')
            ->assertJsonPath('data.tracking_no', 'SF1234567890')
            ->assertJsonPath('data.status', 'shipped');

        $this->assertDatabaseHas('order_shipments', [
            'order_id' => $order->id,
            'carrier' => 'SF',
            'tracking_no' => 'SF1234567890',
            'status' => 'shipped',
        ]);

        // Order 状态推进 + 老字段同步
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Shipped, $fresh->status);
        $this->assertSame('SF1234567890', $fresh->shipping_no);
        $this->assertSame('SF', $fresh->shipping_company);
        $this->assertNotNull($fresh->shipped_at);
    }

    public function test_cannot_ship_pending_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $order = $this->makePaidOrder($tenantId);
        $order->status = OrderStatus::Pending;
        $order->save();

        $response = $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id,
            'carrier' => 'SF',
            'tracking_no' => 'SF000',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('order_shipments', ['order_id' => $order->id]);
    }

    public function test_cannot_ship_cancelled_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);
        $order->status = OrderStatus::Cancelled;
        $order->save();

        $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id,
            'carrier' => 'SF',
            'tracking_no' => 'SF000',
        ])->assertStatus(400);
    }

    public function test_split_shipment_keeps_order_in_shipped(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        // 第一单
        $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'SF', 'tracking_no' => 'P1',
        ])->assertOk();

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);

        // 拆单第二包
        $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'EMS', 'tracking_no' => 'P2',
        ])->assertOk();

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(2, OrderShipment::where('order_id', $order->id)->count());
    }

    public function test_validation_requires_carrier_and_tracking(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        // 缺 carrier
        $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'tracking_no' => 'X',
        ])->assertStatus(422);

        // 缺 tracking_no
        $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'SF',
        ])->assertStatus(422);
    }

    public function test_admin_can_update_tracking(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'carrier' => 'SF',
            'tracking_no' => 'SF_OLD',
            'status' => OrderShipmentStatus::Shipped,
            'shipped_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/mall/order-shipments/{$shipment->id}", [
            'tracking_no' => 'SF_NEW',
            'fee' => 18.00,
        ]);

        $response->assertOk();
        $fresh = $shipment->fresh();
        $this->assertSame('SF_NEW', $fresh->tracking_no);
        $this->assertSame('18.00', (string) $fresh->fee);
    }

    public function test_mark_delivered_transitions_order_to_delivered_when_all_shipped(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        // 直接发一单
        $resp = $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'SF', 'tracking_no' => 'SF1',
        ])->assertOk();
        $sid = $resp->json('data.id');

        $this->postJson("/api/v1/mall/order-shipments/{$sid}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Delivered, $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
    }

    public function test_partial_delivered_keeps_order_in_shipped(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        $r1 = $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'SF', 'tracking_no' => 'P1',
        ]);
        $r2 = $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'EMS', 'tracking_no' => 'P2',
        ]);

        $this->postJson("/api/v1/mall/order-shipments/{$r1->json('data.id')}/deliver")->assertOk();

        // 仅一单签收，订单仍 Shipped
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);

        // 第二单签收 → 订单 Delivered
        $this->postJson("/api/v1/mall/order-shipments/{$r2->json('data.id')}/deliver")->assertOk();
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_cannot_deliver_already_delivered_shipment(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        $shipment = OrderShipment::create([
            'order_id' => $order->id, 'carrier' => 'SF', 'tracking_no' => 'X',
            'status' => OrderShipmentStatus::Delivered, 'shipped_at' => now(), 'delivered_at' => now(),
        ]);

        $this->postJson("/api/v1/mall/order-shipments/{$shipment->id}/deliver")
            ->assertStatus(400);
    }

    public function test_cancel_shipment_does_not_change_order_status(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        $resp = $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $order->id, 'carrier' => 'SF', 'tracking_no' => 'X',
        ]);
        $sid = $resp->json('data.id');

        $this->postJson("/api/v1/mall/order-shipments/{$sid}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Order 状态保持 Shipped（不能自动撤回）
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_index_filters_by_order_id_and_status(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $o1 = $this->makePaidOrder($tenantId);
        $o2 = $this->makePaidOrder($tenantId);

        OrderShipment::create([
            'order_id' => $o1->id, 'carrier' => 'SF', 'tracking_no' => 'A',
            'status' => OrderShipmentStatus::Shipped,
        ]);
        OrderShipment::create([
            'order_id' => $o1->id, 'carrier' => 'SF', 'tracking_no' => 'B',
            'status' => OrderShipmentStatus::Delivered,
        ]);
        OrderShipment::create([
            'order_id' => $o2->id, 'carrier' => 'EMS', 'tracking_no' => 'C',
            'status' => OrderShipmentStatus::Shipped,
        ]);

        $byOrder = $this->getJson("/api/v1/mall/order-shipments?order_id={$o1->id}");
        $byOrder->assertOk();
        $this->assertSame(2, $byOrder->json('data.total'));

        $byStatus = $this->getJson('/api/v1/mall/order-shipments?status=shipped');
        $this->assertSame(2, $byStatus->json('data.total'));
    }

    public function test_admin_cannot_access_other_tenant_shipment(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('oth');
        $otherOrder = $this->makePaidOrder($other->id);
        $otherShipment = OrderShipment::create([
            'order_id' => $otherOrder->id, 'carrier' => 'SF', 'tracking_no' => 'X',
            'status' => OrderShipmentStatus::Shipped,
        ]);

        $this->getJson("/api/v1/mall/order-shipments/{$otherShipment->id}")->assertStatus(403);

        // 也不能给别人的订单发货
        $this->postJson('/api/v1/mall/order-shipments', [
            'order_id' => $otherOrder->id, 'carrier' => 'SF', 'tracking_no' => 'Y',
        ])->assertStatus(403);

        // index 看不到其它租户的发货
        $list = $this->getJson('/api/v1/mall/order-shipments');
        $this->assertSame(0, $list->json('data.total'));
    }

    public function test_customer_can_query_order_with_shipments(): void
    {
        $tenant = $this->createTenant('cus');
        $sessionId = 'sess-cus';
        $order = $this->makePaidOrder($tenant->id, $sessionId);

        OrderShipment::create([
            'order_id' => $order->id,
            'carrier' => 'SF',
            'tracking_no' => 'SF888',
            'status' => OrderShipmentStatus::Shipped,
            'shipped_at' => now(),
            'fee' => 12.50,
        ]);

        $response = $this->getJson("/api/v1/shop/orders/{$order->id}", [
            'X-Tenant-Id' => (string) $tenant->id,
            'X-Session-Id' => $sessionId,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.shipments')
            ->assertJsonPath('data.shipments.0.tracking_no', 'SF888')
            ->assertJsonPath('data.shipments.0.carrier', 'SF')
            ->assertJsonPath('data.shipments.0.status', 'shipped');
    }
}
