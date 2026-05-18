<?php

namespace Tests\Feature\Payment;

use App\Contracts\Payment\PaymentResult;
use App\Contracts\Payment\RefundResult;
use App\Enums\OrderPaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Payment\Drivers\StripeDriver;
use App\Services\Api\Payment\PaymentManager;
use App\Services\Api\Payment\Stripe\StripeClient;
use App\Services\Api\Shop\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\FakeStripeClient;
use Tests\TestCase;

/**
 * StripeDriver 的 charge / refund 单元行为（FakeStripeClient 拦截真实 API 调用）。
 */
class StripeChargeTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeClient $fake;

    private int $tenantId;

    private Order $order;

    private OrderPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeStripeClient;
        $this->app->instance(StripeClient::class, $this->fake);

        $tenant = Tenant::create([
            'code' => 'st-'.uniqid(),
            'name' => 'Stripe Test',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $product = Product::create(['tenant_id' => $this->tenantId, 'base_currency' => 'USD']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'SKU-X', 'price' => 25.00, 'stock' => 10,
        ]);

        $this->order = Order::create([
            'order_no' => 'O-TEST-001',
            'tenant_id' => $this->tenantId,
            'currency' => 'USD',
            'subtotal' => 50.00,
            'total' => 50.00,
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => 'SKU-X',
            'name_snapshot' => 'Widget',
            'unit_price' => 25.00,
            'currency' => 'USD',
            'quantity' => 2,
            'line_total' => 50.00,
        ]);

        $this->payment = OrderPayment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'stripe',
            'transaction_id' => 'pi_existing_001',
            'amount' => 50.00,
            'currency' => 'USD',
            'status' => OrderPaymentStatus::Success,
            'raw_response' => ['payment_intent' => 'pi_existing_001'],
        ]);
    }

    private function makeMethod(array $configOverrides = []): PaymentMethod
    {
        return PaymentMethod::create([
            'tenant_id' => $this->tenantId,
            'code' => 'stripe',
            'driver' => 'stripe',
            'name' => 'Stripe',
            'config' => array_merge([
                'api_key' => 'sk_test_FAKE',
                'webhook_secret' => 'whsec_FAKE',
                'success_url' => 'https://shop.example.com/return',
                'cancel_url' => 'https://shop.example.com/cancel',
            ], $configOverrides),
            'status' => 1,
        ]);
    }

    public function test_paymentmanager_resolves_stripe_driver_via_config(): void
    {
        $this->makeMethod();
        $manager = new PaymentManager;
        $driver = $manager->driver('stripe', $this->tenantId);
        $this->assertInstanceOf(StripeDriver::class, $driver);
    }

    public function test_charge_creates_checkout_session_with_line_items(): void
    {
        $method = $this->makeMethod();
        $this->fake->nextCheckoutSession = [
            'id' => 'cs_test_abc',
            'url' => 'https://checkout.stripe.com/c/pay/abc',
            'payment_intent' => 'pi_abc',
            'raw' => ['id' => 'cs_test_abc', 'object' => 'checkout.session'],
        ];

        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);
        $result = $driver->charge($this->order);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('cs_test_abc', $result->transactionId);
        $this->assertSame('https://checkout.stripe.com/c/pay/abc', $result->payUrl);

        $this->assertCount(1, $this->fake->checkoutCalls);
        $call = $this->fake->checkoutCalls[0];
        $this->assertSame('sk_test_FAKE', $call['key']);
        $this->assertSame('payment', $call['params']['mode']);
        $this->assertSame('usd', $call['params']['line_items'][0]['price_data']['currency']);
        $this->assertSame(2500, $call['params']['line_items'][0]['price_data']['unit_amount']); // 25.00 USD → 2500 cents
        $this->assertSame(2, $call['params']['line_items'][0]['quantity']);
        $this->assertSame('Widget', $call['params']['line_items'][0]['price_data']['product_data']['name']);
        $this->assertSame('O-TEST-001', $call['params']['metadata']['order_no']);
        $this->assertSame('O-TEST-001', $call['params']['client_reference_id']);
    }

    public function test_charge_throws_when_missing_redirect_urls(): void
    {
        $this->makeMethod(['success_url' => '', 'cancel_url' => '']);
        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.stripe_redirect_url_missing');
        $driver->charge($this->order);
    }

    public function test_charge_throws_when_missing_api_key(): void
    {
        $this->makeMethod(['api_key' => '']);
        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_driver_unavailable');
        $driver->charge($this->order);
    }

    public function test_charge_uses_per_request_redirect_url_when_provided(): void
    {
        $this->makeMethod();
        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);

        $driver->charge($this->order, [
            'success_url' => 'https://custom.example/ok',
            'cancel_url' => 'https://custom.example/no',
        ]);

        $this->assertSame('https://custom.example/ok', $this->fake->checkoutCalls[0]['params']['success_url']);
        $this->assertSame('https://custom.example/no', $this->fake->checkoutCalls[0]['params']['cancel_url']);
    }

    public function test_refund_calls_stripe_with_payment_intent_in_minor_units(): void
    {
        $this->makeMethod();
        $this->fake->nextRefund = [
            'id' => 're_test_999',
            'status' => 'succeeded',
            'amount' => 5000,
            'raw' => ['id' => 're_test_999'],
        ];

        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);
        $result = $driver->refund($this->payment, 50.00);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('re_test_999', $result->refundId);
        $this->assertSame(50.0, $result->amount);

        $this->assertCount(1, $this->fake->refundCalls);
        $this->assertSame('pi_existing_001', $this->fake->refundCalls[0]['params']['payment_intent']);
        $this->assertSame(5000, $this->fake->refundCalls[0]['params']['amount']); // 50.00 USD → 5000 cents
    }

    public function test_refund_marks_failed_when_status_not_succeeded(): void
    {
        $this->makeMethod();
        $this->fake->nextRefund = [
            'id' => 're_failed',
            'status' => 'failed',
            'amount' => 5000,
            'raw' => [],
        ];

        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);
        $result = $driver->refund($this->payment, 50.00);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->message);
    }

    public function test_refund_throws_when_payment_intent_missing(): void
    {
        $this->makeMethod();
        $this->payment->update(['raw_response' => []]);

        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.stripe_payment_intent_missing');
        $driver->refund($this->payment->fresh(), 10.00);
    }

    public function test_zero_decimal_currency_does_not_multiply_by_100(): void
    {
        $this->makeMethod();
        $this->order->update(['currency' => 'JPY']);
        OrderItem::query()->where('order_id', $this->order->id)->update(['currency' => 'JPY']);

        $driver = (new PaymentManager)->driver('stripe', $this->tenantId);
        $driver->charge($this->order->fresh());

        // JPY 是零小数币种：unit_amount 不再 *100
        $this->assertSame(25, $this->fake->checkoutCalls[0]['params']['line_items'][0]['price_data']['unit_amount']);
    }

    public function test_pricecalculator_is_available_for_dependency_injection_sanity(): void
    {
        // Sanity 检查容器还能正常解析非 payment 依赖（确认 binding 不污染）
        $this->assertInstanceOf(PriceCalculator::class, app(PriceCalculator::class));
    }
}
