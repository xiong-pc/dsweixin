<?php

namespace App\Listeners\Mall;

use App\Enums\OrderStatus;
use App\Events\Mall\OrderPaidEvent;
use App\Services\Api\Shop\OrderService;
use Illuminate\Support\Facades\Log;

/**
 * 处理订单支付成功事件：状态机转移 + 库存确认扣减 + 通知占位。
 *
 * 幂等性来源：
 *   1. webhook 入口已用 order_payments.transaction_id UNIQUE 拦掉重复
 *   2. 此 Listener 再校验 order.status 已 paid 或 payment.status 非 success → 直接跳过
 */
class HandleOrderPaidListener
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function handle(OrderPaidEvent $event): void
    {
        $order = $event->order;
        $payment = $event->payment;

        // 仅 success 状态的 payment 才推进订单
        if (! $payment->isSuccess()) {
            return;
        }

        // 订单已是终态（paid/shipped/...），跳过
        if ($order->status !== OrderStatus::Pending) {
            return;
        }

        // 复用 OrderService::confirmPayment：转 status=Paid + 调 InventoryService::confirmDeduct
        $this->orderService->confirmPayment($order, (string) $payment->payment_method);

        // 通知占位：P0 仅记日志，P1 接入邮件/站内信/微信模板消息
        Log::info('mall.order.paid', [
            'order_no' => $order->order_no,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'payment_method' => $payment->payment_method,
            'amount' => (string) $payment->amount,
        ]);
    }
}
