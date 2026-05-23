<?php

namespace Tests\Unit\Payment;

use App\Contracts\Payment\PaymentDriverInterface;
use App\Contracts\Payment\PaymentResult;
use App\Contracts\Payment\RefundResult;
use App\Contracts\Payment\WebhookResult;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Services\Api\Payment\Drivers\AbstractPaymentDriver;
use App\Services\Api\Payment\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * PaymentManager 单元测试 —— 关注解析 / 配置 / extend 三件事，
 * 不依赖任何真实支付通道。
 */
class PaymentManagerTest extends TestCase
{
    use RefreshDatabase;

    private PaymentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new PaymentManager;
        // 默认清空 config drivers，避免应用层默认配置干扰
        Config::set('payment.drivers', []);
    }

    private function createMethod(array $overrides = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'tenant_id' => 1,
            'shop_id' => null,
            'code' => 'fake',
            'driver' => 'fake',
            'name' => 'Fake Pay',
            'config' => ['merchant_id' => 'M-001', 'secret' => 's3cr3t'],
            'status' => 1,
            'sort' => 0,
        ], $overrides));
    }

    public function test_resolve_existing_method_returns_driver_with_method_injected(): void
    {
        $this->createMethod();
        $this->manager->extend('fake', fn (PaymentMethod $m) => new FakeDriver($m));

        $driver = $this->manager->driver('fake', 1);

        $this->assertInstanceOf(FakeDriver::class, $driver);
        /** @var FakeDriver $driver */
        $this->assertSame('fake', $driver->code());
        $this->assertSame('M-001', $driver->getConfig('merchant_id'));
    }

    public function test_resolve_nonexistent_code_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_method_not_found');

        $this->manager->driver('ghost', 1);
    }

    public function test_resolve_empty_code_throws(): void
    {
        $this->expectException(BusinessException::class);
        $this->manager->driver('', 1);
    }

    public function test_resolve_disabled_method_throws(): void
    {
        $this->createMethod(['status' => 0]);
        $this->manager->extend('fake', fn (PaymentMethod $m) => new FakeDriver($m));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_method_disabled');

        $this->manager->driver('fake', 1);
    }

    public function test_resolve_unregistered_driver_throws(): void
    {
        // method 存在但 driver 既未 extend 也未在 config 注册
        $this->createMethod(['driver' => 'unknown']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_driver_unavailable');

        $this->manager->driver('fake', 1);
    }

    public function test_resolve_tenant_isolation(): void
    {
        $this->createMethod(['tenant_id' => 1]);
        $this->manager->extend('fake', fn (PaymentMethod $m) => new FakeDriver($m));

        // 租户 2 拿不到租户 1 的 method
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_method_not_found');

        $this->manager->driver('fake', 2);
    }

    public function test_shop_level_overrides_tenant_level(): void
    {
        // 租户级（shop_id NULL）+ shop 级（shop_id=10）同 code，shop 级配置应胜出
        $this->createMethod([
            'shop_id' => null,
            'config' => ['merchant_id' => 'TENANT-LEVEL'],
        ]);
        $this->createMethod([
            'shop_id' => 10,
            'config' => ['merchant_id' => 'SHOP-LEVEL'],
        ]);
        $this->manager->extend('fake', fn (PaymentMethod $m) => new FakeDriver($m));

        $driver = $this->manager->driver('fake', 1, 10);
        $this->assertInstanceOf(FakeDriver::class, $driver);
        /** @var FakeDriver $driver */
        $this->assertSame('SHOP-LEVEL', $driver->getConfig('merchant_id'));
    }

    public function test_shop_falls_back_to_tenant_level_when_shop_record_missing(): void
    {
        // 仅租户级存在，传 shopId 时应回退到租户级
        $this->createMethod([
            'shop_id' => null,
            'config' => ['merchant_id' => 'TENANT-LEVEL'],
        ]);
        $this->manager->extend('fake', fn (PaymentMethod $m) => new FakeDriver($m));

        $driver = $this->manager->driver('fake', 1, 10);
        $this->assertInstanceOf(FakeDriver::class, $driver);
        /** @var FakeDriver $driver */
        $this->assertSame('TENANT-LEVEL', $driver->getConfig('merchant_id'));
    }

    public function test_available_methods_lists_only_enabled_ordered(): void
    {
        $this->createMethod(['code' => 'a', 'sort' => 20, 'status' => 1]);
        $this->createMethod(['code' => 'b', 'sort' => 10, 'status' => 1]);
        $this->createMethod(['code' => 'c', 'sort' => 5, 'status' => 0]); // 禁用不出现

        $methods = $this->manager->availableMethods(1);

        $codes = $methods->pluck('code')->all();
        $this->assertSame(['b', 'a'], $codes); // sort=10 在前，禁用的 c 不出现
    }

    public function test_available_methods_includes_both_tenant_and_shop_level(): void
    {
        $this->createMethod(['code' => 'wechat', 'shop_id' => null]);
        $this->createMethod(['code' => 'stripe', 'shop_id' => 10]);
        $this->createMethod(['code' => 'alipay', 'shop_id' => 20]); // 别的 shop

        $methods = $this->manager->availableMethods(1, 10);
        $codes = $methods->pluck('code')->all();

        sort($codes);
        $this->assertSame(['stripe', 'wechat'], $codes); // 租户级 + 自身 shop 级
    }

    public function test_extend_takes_precedence_over_config(): void
    {
        $this->createMethod(); // driver=fake
        Config::set('payment.drivers.fake', ConfigFakeDriver::class);

        // 同时 extend 一个不同的 fake，应优先于 config 类
        $this->manager->extend('fake', fn (PaymentMethod $m) => new FakeDriver($m));

        $driver = $this->manager->driver('fake', 1);
        $this->assertInstanceOf(FakeDriver::class, $driver);
        $this->assertNotInstanceOf(ConfigFakeDriver::class, $driver);
    }

    public function test_resolves_via_config_class_path_when_no_extend(): void
    {
        $this->createMethod(); // driver=fake
        Config::set('payment.drivers.fake', ConfigFakeDriver::class);

        $driver = $this->manager->driver('fake', 1);

        $this->assertInstanceOf(ConfigFakeDriver::class, $driver);
        /** @var ConfigFakeDriver $driver */
        $this->assertSame('M-001', $driver->getConfig('merchant_id'));
    }

    public function test_config_class_must_implement_interface(): void
    {
        $this->createMethod();
        // 一个不实现 PaymentDriverInterface 的类
        Config::set('payment.drivers.fake', \stdClass::class);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.payment_driver_unavailable');

        $this->manager->driver('fake', 1);
    }
}

/**
 * 内存版 fake driver —— 用 extend 注入。
 */
class FakeDriver extends AbstractPaymentDriver
{
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config($key, $default);
    }

    public function charge(Order $order, array $params = []): PaymentResult
    {
        return new PaymentResult(success: true, transactionId: 'FAKE-TX');
    }

    public function refund(OrderPayment $payment, float $amount): RefundResult
    {
        return new RefundResult(success: true, refundId: 'FAKE-RF', amount: $amount);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        return new WebhookResult(
            success: true,
            eventType: WebhookResult::EVENT_PAYMENT_SUCCESS,
        );
    }
}

/**
 * 通过 config class-path 注册的 driver。
 */
class ConfigFakeDriver extends AbstractPaymentDriver
{
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config($key, $default);
    }

    public function charge(Order $order, array $params = []): PaymentResult
    {
        return new PaymentResult(success: true, transactionId: 'CFG-FAKE-TX');
    }

    public function refund(OrderPayment $payment, float $amount): RefundResult
    {
        return new RefundResult(success: true, refundId: 'CFG-FAKE-RF', amount: $amount);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        return new WebhookResult(
            success: true,
            eventType: WebhookResult::EVENT_PAYMENT_SUCCESS,
        );
    }
}
