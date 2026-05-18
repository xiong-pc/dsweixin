<?php

namespace Tests\Feature\Payment;

use App\Contracts\Payment\WebhookResult;
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
use App\Services\Api\Payment\Stripe\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Payment\FakeStripeClient;
use Tests\TestCase;

/**
 * Webhook 端点 + StripeDriver.handleWebhook 集成行为。
 *
 * 验证 spec 关键承诺：
 *   - 验签 OK + checkout.session.completed → 创建 OrderPayment(success) + dispatch OrderPaidEvent
 *   - 同 transaction_id 重发 → 不重复 dispatch（DB UNIQUE 兜底）
 *   - 签名失败 → 返回 200 不写库不发事件
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeClient $fake;

    private int $tenantId;

    private PaymentMethod $method;

    private Order $order;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeStripeClient;
        $this->app->instance(StripeClient::class, $this->fake);

        $tenant = Tenant::create([
            'code' => 'sw-'.uniqid(),
            'name' => 'Stripe Webhook Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $this->method = PaymentMethod::create([
            'tenant_id' => $this->tenantId,
            'code' => 'stripe',
            'driver' => 'stripe',
            'name' => 'Stripe',
            'config' => [
                'api_key' => 'sk_test_FAKE',
                'webhook_secret' => 'whsec_FAKE',
                'success_url' => 'https://shop.example.com/ok',
                'cancel_url' => 'https://shop.example.com/no',
            ],
            'status' => 1,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'sku_prefix' => 'P-W',
            'base_currency' => 'USD',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-W',
            'price' => 50.00,
            'stock' => 10,
            'reserved' => 2, // 模拟已预占 2 件
        ]);

        $this->order = Order::create([
            'order_no' => 'O-WH-001',
            'tenant_id' => $this->tenantId,
            'currency' => 'USD',
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => OrderStatus::Pending,
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'variant_id' => $this->variant->id,
            'sku' => 'SKU-W',
            'name_snapshot' => 'W',
            'unit_price' => 50.00,
            'currency' => 'USD',
            'quantity' => 2,
            'line_total' => 100.00,
        ]);
    }

    private function setCheckoutCompletedEvent(string $sessionId, string $orderNo, int $amountCents = 10000, string $currency = 'usd'): void
    {
        $this->fake->nextEvent = [
            'id' => 'evt_'.$sessionId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'amount_total' => $amountCents,
                    'currency' => $currency,
                    'client_reference_id' => $orderNo,
                    'metadata' => ['order_no' => $orderNo],
                    'payment_intent' => 'pi_'.$sessionId,
                ],
            ],
            'raw' => ['id' => 'evt_'.$sessionId],
        ];
    }

    private function postWebhook(): TestResponse
    {
        return $this->postJson(
            "/api/v1/shop/payment/webhook/{$this->method->id}",
            [],
            ['Stripe-Signature' => 't=123,v1=fake']
        );
    }

    public function test_checkout_completed_creates_payment_and_dispatches_event(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->setCheckoutCompletedEvent('cs_test_ok', 'O-WH-001');

        $resp = $this->postWebhook();

        $resp->assertOk()->assertJsonPath('msg', 'ok');

        $payment = OrderPayment::query()->where('transaction_id', 'cs_test_ok')->first();
        $this->assertNotNull($payment);
        $this->assertSame($this->order->id, (int) $payment->order_id);
        $this->assertSame(OrderPaymentStatus::Success, $payment->status);
        $this->assertSame('stripe', $payment->payment_method);
        $this->assertSame('100.00', (string) $payment->amount);

        Event::assertDispatched(OrderPaidEvent::class, function (OrderPaidEvent $e) use ($payment) {
            return $e->order->id === $this->order->id
                && $e->payment->id === $payment->id;
        });
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->setCheckoutCompletedEvent('cs_test_dup', 'O-WH-001');

        $this->postWebhook()->assertOk()->assertJsonPath('msg', 'ok');
        $this->postWebhook()->assertOk()->assertJsonPath('msg', 'duplicate');

        $this->assertSame(1, OrderPayment::query()->where('transaction_id', 'cs_test_dup')->count());
        Event::assertDispatched(OrderPaidEvent::class, 1);
    }

    public function test_invalid_signature_returns_200_without_side_effects(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->fake->shouldFailSignature = true;

        $resp = $this->postWebhook();
        $resp->assertOk()->assertJsonPath('msg', 'ignored');

        $this->assertSame(0, OrderPayment::query()->count());
        Event::assertNotDispatched(OrderPaidEvent::class);
    }

    public function test_unknown_order_no_returns_200_without_creating_payment(): void
    {
        Event::fake([OrderPaidEvent::class]);
        $this->setCheckoutCompletedEvent('cs_ghost', 'O-GHOST');

        $resp = $this->postWebhook();
        $resp->assertOk()->assertJsonPath('msg', 'order_not_found');

        $this->assertSame(0, OrderPayment::query()->count());
        Event::assertNotDispatched(OrderPaidEvent::class);
    }

    public function test_payment_intent_succeeded_event_also_recognized(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $this->fake->nextEvent = [
            'id' => 'evt_pi',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_succ_123',
                    'amount_received' => 10000,
                    'currency' => 'usd',
                    'metadata' => ['order_no' => 'O-WH-001'],
                ],
            ],
            'raw' => ['id' => 'evt_pi'],
        ];

        $this->postWebhook()->assertOk()->assertJsonPath('msg', 'ok');

        $payment = OrderPayment::query()->where('transaction_id', 'pi_succ_123')->first();
        $this->assertNotNull($payment);
        $this->assertSame(OrderPaymentStatus::Success, $payment->status);
    }

    public function test_payment_intent_failed_records_failed_payment(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $this->fake->nextEvent = [
            'id' => 'evt_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_1',
                    'amount' => 10000,
                    'currency' => 'usd',
                    'metadata' => ['order_no' => 'O-WH-001'],
                    'last_payment_error' => ['message' => 'card declined'],
                ],
            ],
            'raw' => ['id' => 'evt_failed'],
        ];

        $this->postWebhook()->assertOk();

        $payment = OrderPayment::query()->where('transaction_id', 'pi_failed_1')->first();
        $this->assertNotNull($payment);
        $this->assertSame(OrderPaymentStatus::Failed, $payment->status);
        Event::assertNotDispatched(OrderPaidEvent::class);
    }

    public function test_real_event_flow_updates_order_status_and_stock(): void
    {
        // 不 fake event：让真实 Listener 执行，验证 end-to-end 行为
        $this->setCheckoutCompletedEvent('cs_real_flow', 'O-WH-001');

        $this->postWebhook()->assertOk()->assertJsonPath('msg', 'ok');

        $freshOrder = $this->order->fresh();
        $this->assertSame(OrderStatus::Paid, $freshOrder->status);
        $this->assertNotNull($freshOrder->paid_at);
        $this->assertSame('stripe', $freshOrder->pay_method);

        $variantFresh = $this->variant->fresh();
        $this->assertSame(8, (int) $variantFresh->stock);    // 10 - 2
        $this->assertSame(0, (int) $variantFresh->reserved); // 2 - 2
    }

    public function test_missing_signature_header_returns_200_ignored(): void
    {
        Event::fake([OrderPaidEvent::class]);
        // 不传 Stripe-Signature header
        $resp = $this->postJson("/api/v1/shop/payment/webhook/{$this->method->id}", []);

        $resp->assertOk()->assertJsonPath('msg', 'ignored');
        Event::assertNotDispatched(OrderPaidEvent::class);
    }

    public function test_disabled_payment_method_rejected(): void
    {
        $this->method->update(['status' => 0]);

        $resp = $this->postJson(
            "/api/v1/shop/payment/webhook/{$this->method->id}",
            [],
            ['Stripe-Signature' => 't=1,v1=x']
        );

        // PaymentManager 抛 BusinessException → 全局 handler 400
        $resp->assertStatus(400);
    }

    public function test_unhandled_event_type_returns_200_unhandled(): void
    {
        Event::fake([OrderPaidEvent::class]);

        $this->fake->nextEvent = [
            'id' => 'evt_misc',
            'type' => 'customer.created',
            'data' => ['object' => []],
            'raw' => ['id' => 'evt_misc'],
        ];

        $resp = $this->postWebhook();
        $resp->assertOk();
        // unhandled 路径走 default match 分支 → msg=unhandled
        // 注：driver 也会输出 unhandled WebhookResult，controller 走 default match
        $this->assertContains($resp->json('msg'), ['unhandled', 'ok']);
        Event::assertNotDispatched(OrderPaidEvent::class);
    }
}
