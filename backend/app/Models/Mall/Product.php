<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'shop_id', 'brand_id', 'category_id',
        'sku_prefix', 'cover_image', 'images',
        'base_price', 'base_currency',
        'status', 'sort', 'sold_count', 'view_count',
    ];

    protected $attributes = [
        'sku_prefix' => '',
        'cover_image' => '',
        'base_price' => 0,
        'base_currency' => 'CNY',
        'status' => 0,
        'sort' => 0,
        'sold_count' => 0,
        'view_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'shop_id' => 'integer',
            'brand_id' => 'integer',
            'category_id' => 'integer',
            'images' => 'array',
            'base_price' => 'decimal:2',
            'status' => 'integer',
            'sort' => 'integer',
            'sold_count' => 'integer',
            'view_count' => 'integer',
        ];
    }
}
