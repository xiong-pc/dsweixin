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
use App\Services\Api\Payment\Drivers\WechatDriver;
use App\Services\Api\Payment\PaymentManager;
use App\Services\Api\Payment\Wechat\WechatClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\FakeWechatClient;
use Tests\TestCase;

/**
 * WechatDriver 的 charge / refund 三场景行为（FakeWechatClient 拦截 SDK）。
 */
class WechatChargeTest extends TestCase
{
    use RefreshDatabase;

    private FakeWechatClient $fake;

    private int $tenantId;

    private Order $order;

    private OrderPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeWechatClient;
        $this->app->instance(WechatClient::class, $this->fake);

        $tenant = Tenant::create([
            'code' => 'wx-'.uniqid(),
            'name' => 'Wechat Test',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $product = Product::create(['tenant_id' => $this->tenantId, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'SKU-WX', 'price' => 30.00, 'stock' => 10,
        ]);

        $this->order = Order::create([
            'order_no' => 'O-WX-001',
            'tenant_id' => $this->tenantId,
            'currency' => 'CNY',
            'subtotal' => 60.00,
            'total' => 60.00,
        ]);
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => 'SKU-WX',
            'name_snapshot' => '商品',
            'unit_price' => 30.00,
            'currency' => 'CNY',
            'quantity' => 2,
            'line_total' => 60.00,
        ]);

        $this->payment = OrderPayment::create([
            'order_id' => $this->order->id,
            'payment_method' => 'wechat',
            'transaction_id' => '4200_existing_tx',
            'amount' => 60.00,
            'currency' => 'CNY',
            'status' => OrderPaymentStatus::Success,
            'raw_response' => ['order_no' => 'O-WX-001'],
        ]);
    }

    private function makeMethod(array $configOverrides = []): PaymentMethod
    {
        return PaymentMethod::create([
            'tenant_id' => $this->tenantId,
            'code' => 'wechat',
            'driver' => 'wechat',
            'name' => '微信支付',
            'config' => array_merge([
                'mch_id' => '1234567890',
                'app_id' => 'wx_fake_app_id',
                'mch_secret_key' => 'fake_apiv3_secret_key_32bytes____',
                'mch_secret_cert' => '/fake/apiclient_key.pem',
                'mch_public_cert_path' => ['1A2B3C' => '/fake/apiclient_cert.pem'],
                'notify_url' => 'https://shop.example.com/api/v1/shop/payment/webhook/1',
                'scene' => 'h5',
            ], $configOverrides),
            'status' => 1,
        ]);
    }

    public function test_paymentmanager_resolves_wechat_driver_via_config(): void
    {
        $this->makeMethod();
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $this->assertInstanceOf(WechatDriver::class, $driver);
    }

    public function test_charge_h5_returns_h5_url(): void
    {
        $this->makeMethod(['scene' => 'h5']);
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $result = $driver->charge($this->order);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('O-WX-001', $result->transactionId);
        $this->assertStringContainsString('mwebpay', $result->payUrl);
        $this->assertSame('', $result->payParams); // h5 不返回 payer_params

        $this->assertCount(1, $this->fake->paymentCalls);
        $this->assertSame('h5', $this->fake->paymentCalls[0]['scene']);
        // 金额转分：60.00 CNY → 6000 fen
        $this->assertSame(6000, $this->fake->paymentCalls[0]['params']['amount']['total']);
        $this->assertSame('CNY', $this->fake->paymentCalls[0]['params']['amount']['currency']);
        $this->assertSame('O-WX-001', $this->fake->paymentCalls[0]['params']['out_trade_no']);
        // H5 必须带 scene_info
        $this->assertArrayHasKey('scene_info', $this->fake->paymentCalls[0]['params']);
    }

    public function test_charge_jsapi_returns_payer_params_json(): void
    {
        $this->makeMethod(['scene' => 'jsapi']);
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $result = $driver->charge($this->order, ['openid' => 'oABC123']);

        $this->assertSame('', $result->payUrl);
        $this->assertNotSame('', $result->payParams);

        $decoded = json_decode($result->payParams, true);
        $this->assertIsArray($decoded);
        $this->assertSame('FAKE_SIGN', $decoded['paySign']);
        $this->assertSame('prepay_id=wx_fake', $decoded['package']);

        // JSAPI 必须带 payer.openid
        $this->assertSame('oABC123', $this->fake->paymentCalls[0]['params']['payer']['openid']);
    }

    public function test_charge_native_returns_code_url(): void
    {
        $this->makeMethod(['scene' => 'native']);
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $result = $driver->charge($this->order);

        $this->assertStringContainsString('weixin://', $result->payUrl);
        $this->assertSame('', $result->payParams);
    }

    public function test_charge_per_request_scene_overrides_config(): void
    {
        $this->makeMethod(['scene' => 'h5']);
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $driver->charge($this->order, ['scene' => 'native']);

        $this->assertSame('native', $this->fake->paymentCalls[0]['scene']);
    }

    public function test_charge_throws_when_notify_url_missing(): void
    {
        $this->makeMethod(['notify_url' => '']);
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.wechat_notify_url_missing');
        $driver->charge($this->order);
    }

    public function test_charge_throws_when_required_config_missing(): void
    {
        $this->makeMethod(['mch_id' => '']);
        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_driver_unavailable');
        $driver->charge($this->order);
    }

    public function test_refund_calls_wechat_with_order_no_and_amount_in_fen(): void
    {
        $this->makeMethod();
        $this->fake->nextRefund = [
            'refund_id' => '50000_re_777',
            'status' => 'SUCCESS',
            'amount' => 6000,
            'raw' => ['ok' => true],
        ];

        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);
        $result = $driver->refund($this->payment, 60.00);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('50000_re_777', $result->refundId);
        $this->assertSame(60.0, $result->amount);

        $this->assertCount(1, $this->fake->refundCalls);
        $this->assertSame('O-WX-001', $this->fake->refundCalls[0]['params']['out_trade_no']);
        $this->assertSame(6000, $this->fake->refundCalls[0]['params']['amount']['refund']);
        $this->assertSame(6000, $this->fake->refundCalls[0]['params']['amount']['total']);
        $this->assertSame('CNY', $this->fake->refundCalls[0]['params']['amount']['currency']);
    }

    public function test_refund_processing_status_is_treated_as_success(): void
    {
        $this->makeMethod();
        $this->fake->nextRefund = [
            'refund_id' => 're_proc',
            'status' => 'PROCESSING',
            'amount' => 6000,
            'raw' => [],
        ];

        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);
        $result = $driver->refund($this->payment, 60.00);

        $this->assertTrue($result->success); // 处理中也视为成功（异步出账，由通知更新）
    }

    public function test_refund_other_status_marked_failed(): void
    {
        $this->makeMethod();
        $this->fake->nextRefund = [
            'refund_id' => 're_fail',
            'status' => 'ABNORMAL',
            'amount' => 0,
            'raw' => [],
        ];

        $driver = (new PaymentManager)->driver('wechat', $this->tenantId);
        $result = $driver->refund($this->payment, 60.00);

        $this->assertFalse($result->success);
        $this->assertSame('ABNORMAL', $result->message);
    }
}
