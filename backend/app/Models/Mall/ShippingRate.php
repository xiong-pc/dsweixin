<?php

namespace App\Models\Mall;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 物流方式的分段计费规则（按 zone × 重量区间）。
 *
 * weight_min/max 单位：克（g）；weight_max=0 表示无上限。
 * free_threshold：订单金额 ≥ 该值免运费；0 表示不免。
 *
 * @property int $id
 * @property int $shipping_method_id
 * @property int $zone_id
 * @property int $weight_min
 * @property int $weight_max
 * @property string $price
 * @property string $free_threshold
 */
class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_method_id', 'zone_id', 'weight_min', 'weight_max', 'price', 'free_threshold',
    ];

    protected $attributes = [
        'weight_min' => 0,
        'weight_max' => 0,
        'price' => 0,
        'free_threshold' => 0,
    ];

    protected function casts(): array
    {
        return [
            'shipping_method_id' => 'integer',
            'zone_id' => 'integer',
            'weight_min' => 'integer',
            'weight_max' => 'integer',
            'price' => 'decimal:2',
            'free_threshold' => 'decimal:2',
        ];
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
