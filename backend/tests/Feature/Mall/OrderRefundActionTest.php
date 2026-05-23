<?php

namespace Tests\Feature\Mall;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Payment\Stripe\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\FakeStripeClient;
use Tests\TestCase;

/**
 * Admin `POST /api/v1/mall/orders/{order}/refund` 行为。
 *
 * 走真实 RefundService → driver(Stripe via Fake)，验证：
 *   - 全额退款 → 写 refund OrderPayment + 翻订单到 Refunded + 还库存
 *   - 部分退款 → 仅写 refund 流水，订单状态不变
 *   - reason 透传到 OrderHistory
 *   - tenant 隔离 / 鉴权
 */
class OrderRefundActionTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeStripeClient;
        $this->app->instance(StripeClient::class, $this->fake);
    }

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
    private function makePaidOrder(int $tenantId): array
    {
        PaymentMethod::create([
            'tenant_id' => $tenantId,
            'code' => 'stripe',
            'driver' => 'stripe',
            'name' => 'Stripe',
            'config' => [
                'api_key' => 'sk_test_fake',
                'webhook_secret' => 'whsec_fake',
                'success_url' => 'https://shop.test/success',
                'cancel_url' => 'https://shop.test/cancel',
            ],
            'status' => 1,
        ]);

        $product = Product::create(['tenant_id' => $tenantId, 'base_currency' => 'USD']);
        // 已确认扣减后的库存 (stock 已减 2)
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price' => 30,
            'stock' => 8,
            'reserved' => 0,
        ]);

        $order = Order::create([
            'order_no' => 'O-'.uniqid(),
            'tenant_id' => $tenantId,
            'currency' => 'USD',
            'subtotal' => 60,
            'total' => 60,
            'status' => OrderStatus::Paid,
            'pay_method' => 'stripe',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name_snapshot' => 'X',
            'unit_price' => 30,
            'currency' => 'USD',
            'quantity' => 2,
            'line_total' => 60,
        ]);
        OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'stripe',
            'transaction_id' => 'cs_test_'.uniqid(),
            'amount' => 60,
            'currency' => 'USD',
            'status' => OrderPaymentStatus::Success,
            'paid_at' => now(),
            'raw_response' => ['payment_intent' => 'pi_test_'.uniqid()],
        ]);

        return [$order, $variant];
    }

    public function test_admin_full_refund_flips_order_to_refunded(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order, $variant] = $this->makePaidOrder($tenantId);

        $this->fake->nextRefund = [
            'id' => 're_full_001',
            'status' => 'succeeded',
            'amount' => 6000,
            'raw' => ['object' => 'refund'],
        ];

        $response = $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [
            'reason' => 'customer_complaint',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.order.status', 'refunded');

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        // 库存恢复：8 → 10
        $this->assertSame(10, (int) $variant->fresh()->stock);

        // refund OrderPayment 创建
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'transaction_id' => 're_full_001',
            'status' => 'refunded',
        ]);
    }

    public function test_admin_partial_refund_keeps_order_paid(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order, $variant] = $this->makePaidOrder($tenantId);

        $this->fake->nextRefund = [
            'id' => 're_partial_001',
            'status' => 'succeeded',
            'amount' => 2000,
            'raw' => [],
        ];

        $response = $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [
            'amount' => 20.00,
            'reason' => 'partial_compensation',
        ]);

        $response->assertOk();
        // 部分退款：订单仍 Paid，库存不变
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(8, (int) $variant->fresh()->stock);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'transaction_id' => 're_partial_001',
            'amount' => 20.00,
        ]);
    }

    public function test_refund_reason_propagates_to_order_history(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePaidOrder($tenantId);

        $this->fake->nextRefund = [
            'id' => 're_reason',
            'status' => 'succeeded',
            'amount' => 6000,
            'raw' => [],
        ];

        $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [
            'reason' => 'damaged_goods',
        ])->assertOk();

        // refund_of 在 raw_response 里
        $payment = OrderPayment::where('transaction_id', 're_reason')->first();
        $this->assertNotNull($payment);
        $this->assertSame('damaged_goods', $payment->raw_response['reason'] ?? null);
    }

    public function test_cannot_refund_pending_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePaidOrder($tenantId);
        $order->update(['status' => OrderStatus::Pending]);

        $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [])
            ->assertStatus(400);
    }

    public function test_admin_cannot_refund_other_tenant_order(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('oth');
        [$order] = $this->makePaidOrder($other->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [])
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_refund(): void
    {
        $tenant = $this->createTenant('an');
        [$order] = $this->makePaidOrder($tenant->id);

        $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [])
            ->assertStatus(401);
    }

    public function test_amount_exceeding_source_returns_4xx(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePaidOrder($tenantId);

        // 原支付 60，请求 99.99 触发 invalid_refund_amount
        $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [
            'amount' => 99.99,
        ])->assertStatus(400);
    }

    public function test_validation_amount_must_be_positive(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        [$order] = $this->makePaidOrder($tenantId);

        $this->postJson("/api/v1/mall/orders/{$order->id}/refund", [
            'amount' => 0,
        ])->assertStatus(422);
    }
}
