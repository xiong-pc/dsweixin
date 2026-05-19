<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethodTranslation extends Model
{
    protected $fillable = [
        'shipping_method_id', 'locale', 'name', 'description',
    ];

    protected $attributes = [
        'description' => '',
    ];

    protected function casts(): array
    {
        return [
            'shipping_method_id' => 'integer',
        ];
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}
