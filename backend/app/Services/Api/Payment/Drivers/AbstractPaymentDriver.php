<?php

namespace App\Services\Api\Payment\Drivers;

use App\Contracts\Payment\PaymentDriverInterface;
use App\Models\Mall\PaymentMethod;

/**
 * 支付驱动抽象基类。
 *
 * 各通道驱动（StripeDriver / WechatDriver / …）继承本类即可拿到：
 *   - PaymentMethod 注入（通过 PaymentManager 解析得到）
 *   - config(key, default) helper：读取 PaymentMethod.config 的 dot-path
 *   - code()：当前驱动绑定的 method code
 */
abstract class AbstractPaymentDriver implements PaymentDriverInterface
{
    public function __construct(
        protected readonly PaymentMethod $method,
    ) {}

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function code(): string
    {
        return (string) $this->method->code;
    }

    /**
     * 从 PaymentMethod.config 读取配置项，支持 dot-path。
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        $config = is_array($this->method->config) ? $this->method->config : [];

        return data_get($config, $key, $default);
    }
}
