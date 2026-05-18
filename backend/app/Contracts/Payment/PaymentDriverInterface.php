<?php

namespace App\Contracts\Payment;

use App\Models\Mall\Order;
use App\Models\Mall\OrderPayment;
use Illuminate\Http\Request;

/**
 * 支付驱动统一契约。
 *
 * 各支付通道（Stripe / WechatPay / Alipay / PayPal …）实现此接口，
 * 由 PaymentManager 按 driver code 解析具体实例。
 *
 * 实现类约定：
 *   - 通过构造函数接收 PaymentMethod，自取 config（merchant_id / secret / sandbox）
 *   - charge / refund / handleWebhook 抛出 BusinessException 表示业务失败
 *   - 网络异常透传（由 controller 层 / 队列重试包装）
 */
interface PaymentDriverInterface
{
    /**
     * 发起支付。
     *
     * @param  array<string, mixed>  $params  通道差异化入参（如 returnUrl / openid / paymentMethodId）
     */
    public function charge(Order $order, array $params = []): PaymentResult;

    /**
     * 发起退款。
     */
    public function refund(OrderPayment $payment, float $amount): RefundResult;

    /**
     * 处理异步通知（webhook / notify）。
     *
     * 实现需完成：签名校验 + 事件类型归一化 + 提取 transaction_id / order_no。
     * 业务幂等（防重）由调用方（Listener / Service）依赖 transaction_id 处理。
     */
    public function handleWebhook(Request $request): WebhookResult;
}
