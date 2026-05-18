<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $sku
 * @property string $name_snapshot
 * @property string $image_snapshot
 * @property string $spec_text_snapshot
 * @property string $unit_price
 * @property string $currency
 * @property int $quantity
 * @property string $line_total
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id',
        'sku', 'name_snapshot', 'image_snapshot', 'spec_text_snapshot',
        'unit_price', 'currency', 'quantity', 'line_total',
    ];

    protected $attributes = [
        'image_snapshot' => '',
        'spec_text_snapshot' => '',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
