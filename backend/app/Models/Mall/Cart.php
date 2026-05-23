<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'tenant_id', 'shop_id', 'customer_id', 'session_id', 'locale', 'currency',
    ];

    protected $attributes = [
        'session_id' => '',
        'locale' => 'zh-CN',
        'currency' => 'CNY',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'shop_id' => 'integer',
            'customer_id' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
