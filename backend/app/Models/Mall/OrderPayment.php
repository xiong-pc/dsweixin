<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;

/**
 * 订单支付记录占位模型。
 *
 * PR23 仅建类（让 PaymentDriverInterface::refund 类型签名可引用），
 * 真正的 migration / fillable / 状态机由 M06-PR26 完成。
 */
class OrderPayment extends Model
{
    protected $fillable = [
        'order_id', 'payment_method', 'transaction_id',
        'amount', 'currency', 'status', 'paid_at', 'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }
}
