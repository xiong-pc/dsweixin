<?php

namespace App\Contracts\Payment;

/**
 * Webhook 回调处理的统一返回值。
 *
 * 各通道签名解析 + 业务事件归一化后的输出，方便 Controller 统一处理：
 *   - 事件类型由 driver 翻译为 'payment.success' / 'payment.failed' / 'refund.completed' / 'unknown'
 *   - transaction_id：第三方流水号，用于查 OrderPayment 幂等
 *   - order_no：我方订单号（如果通道传回了）
 */
final readonly class WebhookResult
{
    public const EVENT_PAYMENT_SUCCESS = 'payment.success';

    public const EVENT_PAYMENT_FAILED = 'payment.failed';

    public const EVENT_REFUND_COMPLETED = 'refund.completed';

    public const EVENT_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public string $eventType,
        public ?string $transactionId = null,
        public ?string $orderNo = null,
        public float $amount = 0.0,
        public string $message = '',
        public array $raw = [],
    ) {}

    public static function unknown(array $raw = []): self
    {
        return new self(
            success: false,
            eventType: self::EVENT_UNKNOWN,
            raw: $raw,
        );
    }
}
