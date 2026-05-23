<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Payment\RefundService;
use App\Services\Api\Payment\Stripe\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\FakeStripeClient;
use Tests\TestCase;

/**
 * RefundService 走 Stripe driver 的全流程行为。
 *
 * 涵盖：
 *   - 全额 / 部分退款写流水
 *   - 库存返还（restore: stock += qty）
 *   - 订单状态机转 Refunded
 *   - 各种校验异常
 *   - driver SUCCEEDED vs PENDING vs FAILED 三种响应
 */
class StripeRefundTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeClient $fake;

    private int $tenantId;

    private Order $order;

    private OrderPayment $source;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeStripeClient;
        $this->app->instance(StripeClient::class, $this->fake);

        $tenant = Tenant::create([
            'code' => 'srf-'.uniqid(),
            'name' => 'Stripe Refund Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        PaymentMethod::create([
            'tenant_id' => $this->tenantId,
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

        $product = Product::create(['tenant_id' => $this->tenantId, 'base_currency' => 'USD']);
        // 模拟已确认扣减后的库存：stock 已减 2，reserved 已减 2
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-SRF',
            'price' => 30.00,
            'stock' => 8,    // 原 10，已扣 2
            'reserved' => 0,
        ]);

        $this->order = Order::create([
            'order_no' => 'O-SRF-001',
            'tenant_id' => $this->tenantId,
            'currency' => 'USD',
            'subtotal' => 60.00,
            'total' => 60.00,
            'status' => OrderStatus::Paid,
            'pay_method' => 'stripe',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'variant_id' => $this->variant->id,
            'sku' => 'SKU-SRF',
            'name_snapshot' => 'Test Product',
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 2,
            'line_total' => 60.00,
        ]);

        $this->source = OrderPayment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'stripe',
            'transaction_id' => 'cs_test_paid_001',
            'amount' => 60.00,
            'currency' => 'USD',
            'status' => OrderPaymentStatus::Success,
            'paid_at' => now(),
            'raw_response' => ['payment_intent' => 'pi_test_001'],
        ]);
    }

    public function test_full_refund_writes_refund_payment_row(): void
    {
        $this->fake->nextRefund = [
            'id' => 're_full_001',
            'status' => 'succeeded',
            'amount' => 6000,
            'raw' => ['object' => 'refund'],
        ];

        $refundPayment = app(RefundService::class)->refund($this->order, null, 'customer_request');

        $this->assertSame(OrderPaymentStatus::Refunded, $refundPayment->status);
        $this->assertSame('re_full_001', $refundPayment->transaction_id);
        $this->assertSame('60.00', (string) $refundPayment->amount);
        $this->assertSame('stripe', $refundPayment->payment_method);
        $this->assertSame('cs_test_paid_001', $refundPayment->raw_response['refund_of'] ?? null);
        $this->assertSame('customer_request', $refundPayment->raw_response['reason'] ?? null);
    }

    public function test_full_refund_flips_source_payment_and_order_to_refunded(): void
    {
        app(RefundService::class)->refund($this->order, null, 'damaged');

        $this->assertSame(OrderPaymentStatus::Refunded, $this->source->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $this->order->fresh()->status);
        $this->assertNotNull($this->order->fresh()->refunded_at);
    }

    public function test_full_refund_restores_stock_back_to_original(): void
    {
        app(RefundService::class)->refund($this->order, null);

        // setUp 扣完是 stock=8，restore 2 件后 = 10
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_full_refund_calls_stripe_with_payment_intent_and_minor_units(): void
    {
        app(RefundService::class)->refund($this->order, null);

        $this->assertCount(1, $this->fake->refundCalls);
        $params = $this->fake->refundCalls[0]['params'];
        $this->assertSame('pi_test_001', $params['payment_intent']);
        $this->assertSame(6000, $params['amount']); // USD 60.00 → 6000 cents
    }

    public function test_partial_refund_writes_row_but_keeps_order_paid(): void
    {
        $this->fake->nextRefund = [
            'id' => 're_partial_001',
            'status' => 'succeeded',
            'amount' => 2000,
            'raw' => [],
        ];

        $refundPayment = app(RefundService::class)->refund($this->order, 20.00, 'partial');

        $this->assertSame('20.00', (string) $refundPayment->amount);
        // 源支付不变
        $this->assertSame(OrderPaymentStatus::Success, $this->source->fresh()->status);
        // 订单不变
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
        // 库存不返还
        $this->assertSame(8, (int) $this->variant->fresh()->stock);
    }

    public function test_pending_status_from_stripe_is_treated_as_success(): void
    {
        $this->fake->nextRefund = [
            'id' => 're_pending_001',
            'status' => 'pending',
            'amount' => 6000,
            'raw' => [],
        ];

        $payment = app(RefundService::class)->refund($this->order, null);

        $this->assertSame('re_pending_001', $payment->transaction_id);
        // pending 也视为 success：订单已 Refunded
        $this->assertSame(OrderStatus::Refunded, $this->order->fresh()->status);
    }

    public function test_failed_refund_throws_business_exception(): void
    {
        $this->fake->nextRefund = [
            'id' => 're_fail_001',
            'status' => 'failed',
            'amount' => 0,
            'raw' => [],
        ];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.refund_failed:failed');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_zero_amount_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.invalid_refund_amount');
        app(RefundService::class)->refund($this->order, 0.0);
    }

    public function test_amount_exceeding_source_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.invalid_refund_amount');
        app(RefundService::class)->refund($this->order, 60.01);
    }

    public function test_order_in_pending_status_cannot_refund(): void
    {
        $this->order->status = OrderStatus::Pending;
        $this->order->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.order_cannot_refund');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_order_without_success_payment_cannot_refund(): void
    {
        $this->source->status = OrderPaymentStatus::Failed;
        $this->source->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.no_payment_to_refund');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_refund_on_shipped_order_also_works(): void
    {
        $this->order->status = OrderStatus::Shipped;
        $this->order->save();

        app(RefundService::class)->refund($this->order, null);

        $this->assertSame(OrderStatus::Refunded, $this->order->fresh()->status);
    }

    public function test_jpy_zero_decimal_currency_does_not_multiply_amount(): void
    {
        $this->order->currency = 'JPY';
        $this->order->total = 6000;
        $this->order->subtotal = 6000;
        $this->order->save();
        $this->source->currency = 'JPY';
        $this->source->amount = 6000;
        $this->source->save();

        app(RefundService::class)->refund($this->order, 6000.0);

        $this->assertCount(1, $this->fake->refundCalls);
        // JPY 是 zero-decimal，不 ×100
        $this->assertSame(6000, $this->fake->refundCalls[0]['params']['amount']);
    }
}
