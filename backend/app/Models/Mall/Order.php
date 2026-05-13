<?php

namespace App\Models\Mall;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property OrderStatus $status
 */
class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_no',
        'tenant_id', 'shop_id', 'customer_id', 'session_id',
        'status',
        'currency', 'exchange_rate',
        'subtotal', 'shipping_fee', 'tax_fee', 'discount', 'total',
        'pay_method', 'paid_at',
        'shipped_at', 'shipping_no', 'shipping_company',
        'delivered_at', 'cancelled_at', 'refunded_at',
        'remark',
    ];

    protected $attributes = [
        'status' => 'pending',
        'currency' => 'CNY',
        'exchange_rate' => 1.0,
        'subtotal' => 0,
        'shipping_fee' => 0,
        'tax_fee' => 0,
        'discount' => 0,
        'total' => 0,
        'pay_method' => '',
        'shipping_no' => '',
        'shipping_company' => '',
        'remark' => '',
        'session_id' => '',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'shop_id' => 'integer',
            'customer_id' => 'integer',
            'status' => OrderStatus::class,
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'tax_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }
}
