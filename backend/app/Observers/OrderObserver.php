<?php

namespace App\Observers;

use App\Models\Mall\Order;
use App\Models\Mall\OrderHistory;
use BackedEnum;

/**
 * 自动把每次 Order.status 变更写入 order_histories。
 *
 * 不主动校验合法性 —— 校验由 OrderStateMachine 在转移前完成。
 *
 * 透传 reason/operator 的方式：调用方在 save 前用 OrderObserver::setContext($order, $ctx)
 * 寫入进進程内 spl_object_id 索引的静态表，本 Observer 在 updated() 读取后清理。
 * 选这种方式是为了避免 Eloquent 的动态属性赋值被错误地当作 column 寫进 DB。
 */
class OrderObserver
{
    /**
     * @var array<int, array<string, mixed>> spl_object_id => context
     */
    private static array $contexts = [];

    /**
     * 调用方在 save() 之前调用，把 reason / operator 上下文绑到该 Order 实例上。
     *
     * @param  array<string, mixed>  $context
     */
    public static function setContext(Order $order, array $context): void
    {
        self::$contexts[spl_object_id($order)] = $context;
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $from = $this->extractEnumValue($order->getOriginal('status'));
        $to = $this->extractEnumValue($order->status);

        // 防御性：状态确实没变就不记
        if ($from === $to) {
            return;
        }

        $ctx = self::$contexts[spl_object_id($order)] ?? [];

        OrderHistory::create([
            'order_id' => (int) $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'operator_type' => $this->getString($ctx, 'operator_type', 'system'),
            'operator_id' => $this->getInt($ctx, 'operator_id', 0),
            'reason' => $this->getString($ctx, 'reason', ''),
            'note' => $this->getString($ctx, 'note', ''),
        ]);

        // 一次性上下文：读完清理。避免后续 save 重复写入同一 reason。
        unset(self::$contexts[spl_object_id($order)]);
    }

    private function extractEnumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function getString(array $ctx, string $key, string $default): string
    {
        $val = $ctx[$key] ?? null;

        return is_string($val) ? $val : $default;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function getInt(array $ctx, string $key, int $default): int
    {
        $val = $ctx[$key] ?? null;
        if (is_int($val) || (is_string($val) && ctype_digit($val))) {
            return (int) $val;
        }

        return $default;
    }
}
