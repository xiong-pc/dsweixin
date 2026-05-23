<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';      // 待支付
    case Paid = 'paid';            // 已支付，待发货
    case Shipped = 'shipped';      // 已发货
    case Delivered = 'delivered';  // 已签收
    case Cancelled = 'cancelled';  // 已取消
    case Refunded = 'refunded';    // 已退款

    /**
     * 允许的状态转移图。
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled => [],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Cancelled || $this === self::Refunded || $this === self::Delivered;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s) => $s->value, self::cases());
    }
}
