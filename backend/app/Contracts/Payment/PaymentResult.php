<?php

namespace App\Contracts\Payment;

/**
 * 发起支付的统一返回值。
 *
 * 不同通道在 charge() 之后给前端的内容不同：
 *   - Stripe Checkout：返回 pay_url（重定向）
 *   - Stripe PaymentIntent：返回 client_secret（前端 SDK 用）
 *   - 微信 JSAPI：返回 pay_params（前端 wx.chooseWXPay 调用所需 JSON）
 *   - 微信 H5：返回 pay_url（mweb_url 跳转）
 *
 * 统一承载：success + transaction_id（第三方流水号，用于回调对账）+ pay_url + pay_params。
 */
final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw  第三方原始响应（用于审计/排查）
     */
    public function __construct(
        public bool $success,
        public string $transactionId,
        public string $payUrl = '',
        public string $payParams = '',
        public string $message = '',
        public array $raw = [],
    ) {}

    public static function failure(string $message, array $raw = []): self
    {
        return new self(
            success: false,
            transactionId: '',
            message: $message,
            raw: $raw,
        );
    }
}
