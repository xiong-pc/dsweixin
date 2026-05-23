<?php

namespace App\Events\Mall;

use App\Models\Mall\Order;
use App\Models\Mall\OrderPayment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 订单收款成功事件。
 *
 * 触发时机：webhook 验签通过 + transaction_id 首次出现（幂等通过）后由 driver 层 dispatch。
 * 监听方：HandleOrderPaidListener —— 转移订单状态、扣减库存、发送通知。
 *
 * 这是同步事件（不放队列），保证支付状态变更的强一致性。
 */
class OrderPaidEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderPayment $payment,
    ) {}
}
