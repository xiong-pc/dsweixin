<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 订单状态变更日志（每次 status 转移由 OrderObserver 自动写入）。
 *
 * @property int $id
 * @property int $order_id
 * @property string $from_status
 * @property string $to_status
 * @property string $operator_type
 * @property int $operator_id
 * @property string $reason
 * @property string $note
 * @property Carbon|null $created_at
 */
class OrderHistory extends Model
{
    public const UPDATED_AT = null; // 历史记录不可变，无需 updated_at

    protected $fillable = [
        'order_id', 'from_status', 'to_status',
        'operator_type', 'operator_id', 'reason', 'note',
    ];

    protected $attributes = [
        'operator_type' => 'system',
        'operator_id' => 0,
        'reason' => '',
        'note' => '',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'operator_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
