<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Events\Mall\OrderPaidEvent;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Payment\Wechat\WechatClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Payment\FakeWechatClient;
use Tests\TestCase;

/**
 * 微信支付异步通知 → PaymentWebhookController → OrderPayment + OrderPaidEvent。
 */
class WechatNotifyTest extends TestCase
{
    use RefreshDatabase;

    private FakeWechatClient $fake;

    private int $tenantId;

    private PaymentMethod $method;

    private Order $order;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeWechatClient;
        $this->app->instance(WechatClient::class, $this->fake);

        $tenant = Tenant::create([
            'code' => 'wn-'.uniqid(),
            'name' => 'Wechat Notify Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $this->method = PaymentMethod::create([
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
            'sku' => 'SKU-WN',
            'price' => 30.00,
            'stock' => 10,
            'reserved' => 2,
        ]);

        $this->order = Order::create([
            'order_no' => 'O-WN-001',
            'tenant_id' => $this->tenantId,
            'currency' => 'CNY',
            'subtotal' => 60.00,
            'total' => 60.00,
            'status' => OrderStatus::Pending,
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'variant_id' => $this->variant->id,
            'sku' => 'SKU-WN',
            'name_snapshot' => '商品',
            'unit_price' => 30.00,
            'currency' => 'CNY',
            'quantity' => 2,
            'line_total' => 60.00,
        ]);
    }

    private function setSuccessEvent(string $txnId, string $orderNo, int $totalFen = 6000): void
    {
        $this->fake->nextEvent = [
            'event_type' => 'TRANSACTION.SUCCESS',
            'transaction_id' => $txnId,
            'order_no' => $orderNo,
            'amount' => $totalFen,
            'currency' => 'CNY',
            'raw' => ['id' => 'evt_'.$txnId],
        ];
    }

    private function postNotify(): TestResponse
    {
        return $this->postJson(
            "/api/v1/shop/payment/webhook/{$this->method->id}",
            [],
            [
                'Wechatpay-Signature' => 'fake_sig',
                'Wechatpay-Timestamp' => '1700000000',
                'Wechatpay-Serial' => '1A2B3C',
                'Wechatpay-Nonce' => 'noncexyz',
            ]
        );
    }

    public function test_transaction_success_creates_payment_and_dispatches_event(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->setSuccessEvent('4200_TX_001', 'O-WN-001');

        $resp = $this->postNotify();
        $resp->assertOk()->assertJsonPath('msg', 'ok');

        $payment = OrderPayment::query()->where('transaction_id', '4200_TX_001')->first();
        $this->assertNotNull($payment);
        $this->assertSame(OrderPaymentStatus::Success, $payment->status);
        $this->assertSame('wechat', $payment->payment_method);
        $this->assertSame('60.00', (string) $payment->amount);

        Event::assertDispatched(OrderPaidEvent::class, 1);
    }

    public function test_duplicate_notify_is_idempotent(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->setSuccessEvent('4200_TX_dup', 'O-WN-001');

        $this->postNotify()->assertOk()->assertJsonPath('msg', 'ok');
        $this->postNotify()->assertOk()->assertJsonPath('msg', 'duplicate');

        $this->assertSame(1, OrderPayment::query()->count());
        Event::assertDispatched(OrderPaidEvent::class, 1);
    }

    public function test_invalid_signature_returns_200_ignored(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->fake->shouldFailWebhook = true;

        $resp = $this->postNotify();
        $resp->assertOk()->assertJsonPath('msg', 'ignored');

        $this->assertSame(0, OrderPayment::query()->count());
        Event::assertNotDispatched(OrderPaidEvent::class);
    }

    public function test_unknown_order_no_returns_200_without_creating_payment(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->setSuccessEvent('4200_ghost', 'O-GHOST');

        $resp = $this->postNotify();
        $resp->assertOk()->assertJsonPath('msg', 'order_not_found');

        $this->assertSame(0, OrderPayment::query()->count());
    }

    public function test_real_listener_flow_marks_order_paid_and_deducts_stock(): void
    {
        $this->setSuccessEvent('4200_real', 'O-WN-001');

        $this->postNotify()->assertOk()->assertJsonPath('msg', 'ok');

        $fresh = $this->order->fresh();
        $this->assertSame(OrderStatus::Paid, $fresh->status);
        $this->assertSame('wechat', $fresh->pay_method);
        $this->assertNotNull($fresh->paid_at);

        $variantFresh = $this->variant->fresh();
        $this->assertSame(8, (int) $variantFresh->stock);
        $this->assertSame(0, (int) $variantFresh->reserved);
    }

    public function test_refund_success_event_returns_refund_received(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $this->fake->nextEvent = [
            'event_type' => 'REFUND.SUCCESS',
            'transaction_id' => '4200_refund_1',
            'order_no' => 'O-WN-001',
            'amount' => 6000,
            'currency' => 'CNY',
            'raw' => ['id' => 'evt_refund'],
        ];

        $resp = $this->postNotify();
        $resp->assertOk()->assertJsonPath('msg', 'refund_received');
        Event::assertNotDispatched(OrderPaidEvent::class);
    }

    public function test_unknown_event_type_returns_200_unhandled(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $this->fake->nextEvent = [
            'event_type' => 'SOMETHING.STRANGE',
            'transaction_id' => '4200_strange',
            'order_no' => 'O-WN-001',
            'amount' => 6000,
            'currency' => 'CNY',
            'raw' => ['id' => 'evt_strange'],
        ];

        $resp = $this->postNotify();
        $resp->assertOk();
        $this->assertSame('unhandled', $resp->json('msg'));
    }

    public function test_amount_in_fen_is_converted_to_yuan(): void
    {
        // 9999 分 = 99.99 元
        $this->setSuccessEvent('4200_amt', 'O-WN-001', 9999);
        // 调整订单金额匹配
        $this->order->update(['total' => 99.99]);

        $this->postNotify()->assertOk()->assertJsonPath('msg', 'ok');

        $payment = OrderPayment::query()->where('transaction_id', '4200_amt')->first();
        $this->assertNotNull($payment);
        $this->assertSame('99.99', (string) $payment->amount);
    }
}
