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
use App\Services\Api\Payment\Wechat\WechatClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\FakeWechatClient;
use Tests\TestCase;

/**
 * RefundService 走 WechatDriver 的退款链路。
 *
 * 重点对照 StripeRefundTest：
 *   - 微信用「分」为最小单位（CNY 不变；JPY/KRW 不适用，微信只接 CNY）
 *   - SUCCESS / PROCESSING 都视为成功（异步出账）
 *   - refund_id 不同前缀（50000xxx）
 */
class WechatRefundTest extends TestCase
{
    use RefreshDatabase;

    private FakeWechatClient $fake;

    private int $tenantId;

    private Order $order;

    private OrderPayment $source;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeWechatClient;
        $this->app->instance(WechatClient::class, $this->fake);

        $tenant = Tenant::create([
            'code' => 'wrf-'.uniqid(),
            'name' => 'Wechat Refund Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        PaymentMethod::create([
            'tenant_id' => $this->tenantId,
            'code' => 'wechat',
            'driver' => 'wechat',
            'name' => '微信支付',
            'config' => [
                'mch_id' => '1234567890',
                'app_id' => 'wx_fake',
                'mch_secret_key' => 'fake_apiv3_secret_key_32bytes____',
                'mch_secret_cert' => '/fake/key.pem',
                'mch_public_cert_path' => ['1A2B' => '/fake/cert.pem'],
                'notify_url' => 'https://shop.example.com/api/v1/shop/payment/webhook/1',
                'scene' => 'h5',
            ],
            'status' => 1,
        ]);

        $product = Product::create(['tenant_id' => $this->tenantId, 'base_currency' => 'CNY']);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-WRF',
            'price' => 30.00,
            'stock' => 8,    // 假设已确认扣减 2 件
            'reserved' => 0,
        ]);

        $this->order = Order::create([
            'order_no' => 'O-WRF-001',
            'tenant_id' => $this->tenantId,
            'currency' => 'CNY',
            'subtotal' => 60.00,
            'total' => 60.00,
            'status' => OrderStatus::Paid,
            'pay_method' => 'wechat',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'variant_id' => $this->variant->id,
            'sku' => 'SKU-WRF',
            'name_snapshot' => '测试商品',
            'unit_price' => 30.00,
            'currency' => 'CNY',
            'quantity' => 2,
            'line_total' => 60.00,
        ]);

        $this->source = OrderPayment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'wechat',
            'transaction_id' => '4200001234567890',
            'amount' => 60.00,
            'currency' => 'CNY',
            'status' => OrderPaymentStatus::Success,
            'paid_at' => now(),
            'raw_response' => ['order_no' => 'O-WRF-001'],
        ]);
    }

    public function test_full_refund_writes_refund_payment_and_calls_wechat_in_fen(): void
    {
        $this->fake->nextRefund = [
            'refund_id' => '50000000000123',
            'status' => 'SUCCESS',
            'amount' => 6000,
            'raw' => ['source' => 'wechat_refund'],
        ];

        $refundPayment = app(RefundService::class)->refund($this->order, null, 'quality_issue');

        $this->assertSame('50000000000123', $refundPayment->transaction_id);
        $this->assertSame(OrderPaymentStatus::Refunded, $refundPayment->status);
        $this->assertSame('wechat', $refundPayment->payment_method);

        $this->assertCount(1, $this->fake->refundCalls);
        $params = $this->fake->refundCalls[0]['params'];
        $this->assertSame('O-WRF-001', $params['out_trade_no']);
        $this->assertSame(6000, $params['amount']['refund']);
        $this->assertSame(6000, $params['amount']['total']);
        $this->assertSame('CNY', $params['amount']['currency']);
    }

    public function test_full_refund_marks_order_refunded_and_restores_stock(): void
    {
        $this->fake->nextRefund = [
            'refund_id' => '50000_full',
            'status' => 'SUCCESS',
            'amount' => 6000,
            'raw' => [],
        ];

        app(RefundService::class)->refund($this->order, null);

        $this->assertSame(OrderStatus::Refunded, $this->order->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Refunded, $this->source->fresh()->status);
        // setUp stock=8 → 退 2 件 → 10
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_processing_status_is_treated_as_success(): void
    {
        $this->fake->nextRefund = [
            'refund_id' => '50000_processing',
            'status' => 'PROCESSING',
            'amount' => 6000,
            'raw' => [],
        ];

        $payment = app(RefundService::class)->refund($this->order, null);

        // PROCESSING 也视为成功（异步出账，由后续 webhook 兜底）
        $this->assertSame('50000_processing', $payment->transaction_id);
        $this->assertSame(OrderStatus::Refunded, $this->order->fresh()->status);
    }

    public function test_abnormal_status_throws_refund_failed(): void
    {
        $this->fake->nextRefund = [
            'refund_id' => '50000_abnormal',
            'status' => 'ABNORMAL',
            'amount' => 0,
            'raw' => [],
        ];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.refund_failed:ABNORMAL');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_partial_refund_only_writes_row(): void
    {
        $this->fake->nextRefund = [
            'refund_id' => '50000_partial',
            'status' => 'SUCCESS',
            'amount' => 1500,
            'raw' => [],
        ];

        $refundPayment = app(RefundService::class)->refund($this->order, 15.00, 'partial');

        $this->assertSame('15.00', (string) $refundPayment->amount);
        $this->assertSame(OrderPaymentStatus::Success, $this->source->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $this->order->fresh()->status);
        $this->assertSame(8, (int) $this->variant->fresh()->stock); // 库存不变

        // 微信侧 amount.refund 应当是 1500 分（15 元）
        $this->assertSame(1500, $this->fake->refundCalls[0]['params']['amount']['refund']);
        $this->assertSame(6000, $this->fake->refundCalls[0]['params']['amount']['total']);
    }

    public function test_refund_amount_zero_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.invalid_refund_amount');
        app(RefundService::class)->refund($this->order, 0.0);
    }

    public function test_refund_amount_exceeding_source_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.invalid_refund_amount');
        app(RefundService::class)->refund($this->order, 100.0);
    }

    public function test_pending_order_cannot_refund(): void
    {
        $this->order->status = OrderStatus::Pending;
        $this->order->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.order_cannot_refund');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_cancelled_order_cannot_refund(): void
    {
        $this->order->status = OrderStatus::Cancelled;
        $this->order->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.order_cannot_refund');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_no_success_payment_throws(): void
    {
        $this->source->status = OrderPaymentStatus::Failed;
        $this->source->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.no_payment_to_refund');
        app(RefundService::class)->refund($this->order, null);
    }

    public function test_delivered_order_can_refund(): void
    {
        $this->order->status = OrderStatus::Delivered;
        $this->order->save();

        $this->fake->nextRefund = [
            'refund_id' => '50000_delivered',
            'status' => 'SUCCESS',
            'amount' => 6000,
            'raw' => [],
        ];

        app(RefundService::class)->refund($this->order, null);

        $this->assertSame(OrderStatus::Refunded, $this->order->fresh()->status);
    }

    public function test_raw_response_contains_refund_of_and_reason(): void
    {
        $this->fake->nextRefund = [
            'refund_id' => '50000_meta',
            'status' => 'SUCCESS',
            'amount' => 6000,
            'raw' => ['gateway_seq' => 'abc123'],
        ];

        $payment = app(RefundService::class)->refund($this->order, null, 'customer_complaint');

        $this->assertSame('4200001234567890', $payment->raw_response['refund_of'] ?? null);
        $this->assertSame('customer_complaint', $payment->raw_response['reason'] ?? null);
        $this->assertSame('abc123', $payment->raw_response['gateway_seq'] ?? null);
    }
}
