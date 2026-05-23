<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id', 'type',
        'country_code', 'province', 'city', 'district', 'street',
        'postal_code', 'contact_name', 'contact_phone', 'contact_email',
    ];

    protected $attributes = [
        'type' => 'shipping',
        'province' => '',
        'city' => '',
        'district' => '',
        'postal_code' => '',
        'contact_email' => '',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
