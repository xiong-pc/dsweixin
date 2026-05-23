<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'barcode',
        'price', 'compare_at_price', 'cost',
        'weight', 'weight_unit', 'dimensions',
        'stock', 'reserved', 'low_stock_threshold',
        'image', 'status', 'sort',
    ];

    protected $attributes = [
        'barcode' => '',
        'price' => 0,
        'weight' => 0,
        'weight_unit' => 'g',
        'stock' => 0,
        'reserved' => 0,
        'low_stock_threshold' => 0,
        'image' => '',
        'status' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'weight' => 'decimal:3',
            'dimensions' => 'array',
            'stock' => 'integer',
            'reserved' => 'integer',
            'low_stock_threshold' => 'integer',
            'status' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function specificationValues(): BelongsToMany
    {
        return $this->belongsToMany(
            SpecificationValue::class,
            'product_variant_specification_values',
            'product_variant_id',
            'specification_value_id'
        )->withTimestamps();
    }

    /**
     * 可用库存（stock - reserved）。
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->reserved);
    }
}
