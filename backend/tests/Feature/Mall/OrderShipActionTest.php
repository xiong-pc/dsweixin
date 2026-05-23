<?php

namespace Tests\Feature\Mall;

use App\Enums\OrderPaymentStatus;
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
 * Admin `POST /api/v1/mall/orders/{order}/ship` 行为。
 *
 * 复用 OrderShipmentService，重点验证 controller 端：
 *   - 鉴权 / tenant 隔离
 *   - 校验规则（carrier/tracking_no 必填）
 *   - 状态机限制（pending 不能发货）
 *   - 成功后订单状态 → Shipped + 创建 shipment 记录
 */
class OrderShipActionTest extends TestCase
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

    private function makePaidOrder(int $tenantId): Order
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
            'currency' => 'CNY',
            'subtotal' => 100,
            'total' => 100,
            'status' => OrderStatus::Paid,
            'pay_method' => 'wechat',
            'paid_at' => now(),
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
        OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'wechat',
            'transaction_id' => 'TX-'.uniqid(),
            'amount' => 100,
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

        $response = $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'SF',
            'tracking_no' => 'SF-ADMIN-001',
            'fee' => 15.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonCount(1, 'data.shipments');

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertDatabaseHas('order_shipments', [
            'order_id' => $order->id,
            'tracking_no' => 'SF-ADMIN-001',
        ]);
    }

    public function test_cannot_ship_pending_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);
        $order->update(['status' => OrderStatus::Pending]);

        $response = $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'SF',
            'tracking_no' => 'X',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('order_shipments', ['order_id' => $order->id]);
    }

    public function test_validation_requires_carrier_and_tracking(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'tracking_no' => 'X',
        ])->assertStatus(422);

        $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'SF',
        ])->assertStatus(422);
    }

    public function test_admin_cannot_ship_other_tenant_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('oth');
        $order = $this->makePaidOrder($other->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'SF',
            'tracking_no' => 'X',
        ])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_ship(): void
    {
        $tenant = $this->createTenant('an');
        $order = $this->makePaidOrder($tenant->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'SF',
            'tracking_no' => 'X',
        ])->assertStatus(401);
    }

    public function test_super_admin_can_ship_any_tenant_order(): void
    {
        $this->actingAsSuperAdmin();

        $tenant = $this->createTenant('any');
        $order = $this->makePaidOrder($tenant->id);

        $response = $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'EMS',
            'tracking_no' => 'EMS-001',
        ]);

        $response->assertOk();
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_split_shipment_allowed_when_already_shipped(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $order = $this->makePaidOrder($tenantId);

        // 第一单
        $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'SF', 'tracking_no' => 'P1',
        ])->assertOk();

        // 拆单第二包
        $this->postJson("/api/v1/mall/orders/{$order->id}/ship", [
            'carrier' => 'EMS', 'tracking_no' => 'P2',
        ])->assertOk();

        $this->assertSame(2, OrderShipment::where('order_id', $order->id)->count());
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }
}
