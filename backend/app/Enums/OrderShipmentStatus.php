<?php

namespace App\Enums;

enum OrderShipmentStatus: string
{
    case Shipped = 'shipped';        // 已发货
    case Delivered = 'delivered';    // 已签收
    case Cancelled = 'cancelled';    // 已取消（误发 / 撤回）

    /**
     * 允许的状态转移图。
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Shipped => [self::Delivered, self::Cancelled],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s) => $s->value, self::cases());
    }
}
