<?php

namespace App\Contracts\Payment;

/**
 * 退款发起的统一返回值。
 */
final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public string $refundId,
        public float $amount,
        public string $message = '',
        public array $raw = [],
    ) {}

    public static function failure(string $message, array $raw = []): self
    {
        return new self(
            success: false,
            refundId: '',
            amount: 0.0,
            message: $message,
            raw: $raw,
        );
    }
}
