<?php

use App\Services\Api\Payment\Drivers\StripeDriver;
use App\Services\Api\Payment\Drivers\WechatDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | 支付驱动映射
    |--------------------------------------------------------------------------
    |
    | key 为 payment_methods.driver 字段值，value 为实现 PaymentDriverInterface
    | 的具体类（一般继承 AbstractPaymentDriver）。
    |
    | M06-PR23 仅建抽象，具体驱动由 PR24（Stripe）/ PR25（微信）填充。
    | 测试 / 自定义场景可用 PaymentManager::extend(code, factory) 运行时注册。
    |
    */
    'drivers' => [
        'stripe' => StripeDriver::class,
        'wechat' => WechatDriver::class,
    ],
];
