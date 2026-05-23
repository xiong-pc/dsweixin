<?php

namespace App\Models\Mall;

use App\Enums\OrderPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 订单支付流水（第三方网关每次成功收款 / 退款产生一条）。
 *
 * @property int $id
 * @property int $order_id
 * @property string $payment_method
 * @property string $transaction_id
 * @property string $amount
 * @property string $currency
 * @property OrderPaymentStatus $status
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $raw_response
 */
class OrderPayment extends Model
{
    protected $fillable = [
        'order_id', 'payment_method', 'transaction_id',
        'amount', 'currency', 'status', 'paid_at', 'raw_response',
    ];

    protected $attributes = [
        'currency' => 'CNY',
        'status' => 'pending',
        'amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => OrderPaymentStatus::class,
            'paid_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === OrderPaymentStatus::Success;
    }
}
