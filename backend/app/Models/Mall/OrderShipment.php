<?php

namespace App\Models\Mall;

use App\Enums\OrderShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 订单发货记录（一个订单可拆分多包，对应多条 shipment）。
 *
 * @property int $id
 * @property int $order_id
 * @property string $carrier
 * @property string $tracking_no
 * @property OrderShipmentStatus $status
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property string $fee
 * @property array<string, mixed>|null $raw_response
 */
class OrderShipment extends Model
{
    protected $fillable = [
        'order_id', 'carrier', 'tracking_no', 'status',
        'shipped_at', 'delivered_at', 'fee', 'raw_response',
    ];

    protected $attributes = [
        'carrier' => '',
        'tracking_no' => '',
        'status' => 'shipped',
        'fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'status' => OrderShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'fee' => 'decimal:2',
            'raw_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === OrderShipmentStatus::Delivered;
    }
}
