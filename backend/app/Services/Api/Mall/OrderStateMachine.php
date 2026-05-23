<?php

namespace App\Services\Api\Mall;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;

/**
 * 订单状态机（M08-PR31）。
 *
 * 状态转移图：
 *   pending → paid → shipped → delivered
 *           ↓        ↓        ↓
 *        cancelled refunded refunded
 *
 * 实际枚举来源：{@see OrderStatus::allowedTransitions()}。
 * 本服务把校验逻辑从 enum 里抽出来，便于 Controller / Listener 共用 +
 * 后续接入操作人 / 原因审计（OrderHistory 自动由 OrderObserver 写）。
 */
class OrderStateMachine
{
    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }

    /**
     * @throws BusinessException
     */
    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new BusinessException('api.invalid_order_status_transition');
        }
    }

    /**
     * 给某个订单的下一步可走的状态列表（用于前端 UI 渲染按钮）。
     *
     * @return array<int, string>
     */
    public function nextStates(Order $order): array
    {
        return array_map(
            static fn (OrderStatus $s) => $s->value,
            $order->status->allowedTransitions(),
        );
    }
}
