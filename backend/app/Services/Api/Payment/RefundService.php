<?php

namespace App\Services\Api\Payment;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderItem;
use App\Models\Mall\OrderPayment;
use App\Services\Api\Shop\InventoryService;
use App\Services\Api\Shop\OrderService;
use Illuminate\Support\Facades\DB;

/**
 * 订单退款编排服务（M06-PR27）。
 *
 * 入口：admin 后台 / 客服在订单详情页点击「退款」。
 *
 * 流程：
 *   1. 校验订单状态 ∈ [Paid, Shipped, Delivered]
 *   2. 找最新一条 Success 状态 OrderPayment 作为源支付
 *   3. PaymentManager 按 payment_method 解析对应 driver
 *   4. driver.refund(source, amount)  ←—— 真实 HTTP 调用，发生在 DB 事务外
 *   5. 成功 → 事务内：写 refund OrderPayment + 翻转 source 状态 + 转订单状态 + 还库存
 *
 * 全额退款（amount = null 或 = source.amount）：
 *   - 源 OrderPayment.status → Refunded
 *   - Order.status → Refunded（OrderService.transitionStatus 走状态机校验）
 *   - 所有 OrderItem 调 InventoryService::restore(stock += qty)
 *
 * 部分退款（0 < amount < source.amount）：
 *   - 仅写一条新的 Refunded OrderPayment 流水
 *   - 源支付保持 Success，订单状态不变
 *   - P0 不还库存（部分退款多为售后场景，商品已寄出）
 */
class RefundService
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly OrderService $orderService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * 对一个已成功支付的订单发起退款。
     *
     * @param  float|null  $amount  退款金额；null = 全额
     * @param  string  $reason  退款原因（写入 raw_response）
     *
     * @throws BusinessException
     */
    public function refund(Order $order, ?float $amount = null, string $reason = ''): OrderPayment
    {
        if (! $order->status->canTransitionTo(OrderStatus::Refunded)
            && $order->status !== OrderStatus::Refunded) {
            throw new BusinessException('api.order_cannot_refund');
        }

        $source = OrderPayment::query()
            ->where('order_id', $order->id)
            ->where('status', OrderPaymentStatus::Success->value)
            ->orderByDesc('id')
            ->first();

        if ($source === null) {
            throw new BusinessException('api.no_payment_to_refund');
        }

        $sourceAmount = (float) $source->amount;
        $refundAmount = $amount ?? $sourceAmount;

        if ($refundAmount <= 0 || $refundAmount > $sourceAmount) {
            throw new BusinessException('api.invalid_refund_amount');
        }

        $isFullRefund = $this->isAmountEqual($refundAmount, $sourceAmount);

        $driver = $this->paymentManager->driver(
            (string) $source->payment_method,
            (int) $order->tenant_id,
            $order->shop_id !== null ? (int) $order->shop_id : null,
        );

        // 真实网关调用在事务外，避免长事务持锁
        $result = $driver->refund($source, $refundAmount);

        if (! $result->success) {
            throw new BusinessException(
                'api.refund_failed:'.($result->message !== '' ? $result->message : 'unknown')
            );
        }

        return DB::transaction(function () use ($order, $source, $result, $refundAmount, $reason, $isFullRefund) {
            $refundPayment = OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => (string) $source->payment_method,
                'transaction_id' => $result->refundId,
                'amount' => $refundAmount,
                'currency' => (string) $source->currency,
                'status' => OrderPaymentStatus::Refunded,
                'paid_at' => now(),
                'raw_response' => array_merge($result->raw, [
                    'refund_of' => (string) $source->transaction_id,
                    'reason' => $reason,
                ]),
            ]);

            if ($isFullRefund) {
                $source->status = OrderPaymentStatus::Refunded;
                $source->save();

                // 订单可能已 Paid / Shipped / Delivered，状态机已允许 → Refunded
                if ($order->status !== OrderStatus::Refunded) {
                    $this->orderService->transitionStatus($order, OrderStatus::Refunded);
                }

                /** @var OrderItem $item */
                foreach ($order->items()->get() as $item) {
                    $this->inventoryService->restore(
                        (int) $item->variant_id,
                        (int) $item->quantity,
                    );
                }
            }

            return $refundPayment;
        });
    }

    /**
     * 比较金额时用 epsilon，避免浮点 60.00 != 60.0 假阴性。
     */
    private function isAmountEqual(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }
}
