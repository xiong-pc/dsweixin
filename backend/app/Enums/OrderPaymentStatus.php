<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Pending = 'pending';    // 已发起，未确认（charge 返回 / 等待 webhook）
    case Success = 'success';    // 收款成功（webhook 验证通过）
    case Failed = 'failed';      // 收款失败 / 取消
    case Refunded = 'refunded';  // 已退款

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Success, self::Failed],
            self::Success => [self::Refunded],
            self::Failed => [],
            self::Refunded => [],
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
