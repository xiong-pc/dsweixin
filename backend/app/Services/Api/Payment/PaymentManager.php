<?php

namespace App\Services\Api\Payment;

use App\Contracts\Payment\PaymentDriverInterface;
use App\Exceptions\BusinessException;
use App\Models\Mall\PaymentMethod;
use App\Services\Api\Payment\Drivers\AbstractPaymentDriver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;

/**
 * 支付驱动工厂 / 解析器。
 *
 * 使用方式：
 *   $driver = app(PaymentManager::class)->driver('stripe', $tenantId, $shopId);
 *   $result = $driver->charge($order, $params);
 *
 * 解析顺序（同 code 优先 shop 级、回退租户级）：
 *   1. (tenant_id=X, shop_id=Y, code=Z) 启用记录
 *   2. (tenant_id=X, shop_id=NULL, code=Z) 启用记录
 *   否则抛 BusinessException。
 *
 * 测试 / 自定义通道：
 *   $manager->extend('fake', fn(PaymentMethod $m) => new FakeDriver($m));
 *   注册的工厂优先于 config/payment.php 的 drivers map。
 */
class PaymentManager
{
    /**
     * 运行时注册的驱动工厂：driverCode => fn(PaymentMethod) => PaymentDriverInterface。
     *
     * @var array<string, callable(PaymentMethod): PaymentDriverInterface>
     */
    private array $customDrivers = [];

    /**
     * 注册自定义驱动工厂（测试或扩展用）。
     *
     * @param  callable(PaymentMethod): PaymentDriverInterface  $factory
     */
    public function extend(string $driverCode, callable $factory): void
    {
        $this->customDrivers[$driverCode] = $factory;
    }

    /**
     * 解析驱动实例。
     *
     * @throws BusinessException 方法不存在 / 已禁用 / 驱动未注册
     */
    public function driver(string $code, int $tenantId, ?int $shopId = null): PaymentDriverInterface
    {
        $method = $this->resolveMethod($code, $tenantId, $shopId);

        return $this->makeDriver($method);
    }

    /**
     * 列出当前 tenant + shop 可用的支付方式（已启用、按 sort 升序）。
     *
     * @return Collection<int, PaymentMethod>
     */
    public function availableMethods(int $tenantId, ?int $shopId = null): Collection
    {
        return PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->where(function ($q) use ($shopId) {
                $q->whereNull('shop_id');
                if ($shopId !== null) {
                    $q->orWhere('shop_id', $shopId);
                }
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * 直接基于已加载的 PaymentMethod 实例化驱动（绕过 DB 查询）。
     */
    public function makeDriver(PaymentMethod $method): PaymentDriverInterface
    {
        if (! $method->isEnabled()) {
            throw new BusinessException('api.payment_method_disabled');
        }

        $driverCode = (string) $method->driver;
        if ($driverCode === '') {
            throw new BusinessException('api.payment_driver_unavailable');
        }

        // 1. 优先使用运行时注册的工厂
        if (isset($this->customDrivers[$driverCode])) {
            return ($this->customDrivers[$driverCode])($method);
        }

        // 2. 回退到 config/payment.php drivers map
        $class = (string) Config::get("payment.drivers.{$driverCode}", '');
        if ($class === '' || ! class_exists($class)) {
            throw new BusinessException('api.payment_driver_unavailable');
        }

        if (! is_subclass_of($class, AbstractPaymentDriver::class)
            && ! is_subclass_of($class, PaymentDriverInterface::class)
        ) {
            throw new BusinessException('api.payment_driver_unavailable');
        }

        // 走容器解析以便注入 driver 自身依赖（如 StripeClient），$method 作为 PaymentMethod 参数
        /** @var PaymentDriverInterface $instance */
        $instance = app()->make($class, ['method' => $method]);

        return $instance;
    }

    /**
     * 按 (tenant, shop, code) 查找启用的 PaymentMethod，shop 级优先于租户级。
     *
     * @throws BusinessException
     */
    private function resolveMethod(string $code, int $tenantId, ?int $shopId): PaymentMethod
    {
        if ($code === '') {
            throw new BusinessException('api.payment_method_not_found');
        }

        // shop 级优先
        if ($shopId !== null) {
            $shopLevel = PaymentMethod::query()
                ->where('tenant_id', $tenantId)
                ->where('shop_id', $shopId)
                ->where('code', $code)
                ->first();

            if ($shopLevel !== null) {
                if (! $shopLevel->isEnabled()) {
                    throw new BusinessException('api.payment_method_disabled');
                }

                return $shopLevel;
            }
        }

        // 租户级回退
        $tenantLevel = PaymentMethod::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('shop_id')
            ->where('code', $code)
            ->first();

        if ($tenantLevel === null) {
            throw new BusinessException('api.payment_method_not_found');
        }

        if (! $tenantLevel->isEnabled()) {
            throw new BusinessException('api.payment_method_disabled');
        }

        return $tenantLevel;
    }
}
