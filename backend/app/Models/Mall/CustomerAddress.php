<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客户地址簿。
 *
 * @property int $id
 * @property int $customer_id
 * @property string $label
 * @property string $country_code
 * @property string $province
 * @property string $city
 * @property string $district
 * @property string $street
 * @property string $postal_code
 * @property string $contact_name
 * @property string $contact_phone
 * @property string $contact_email
 * @property int $is_default
 */
class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'label',
        'country_code', 'province', 'city', 'district', 'street',
        'postal_code', 'contact_name', 'contact_phone', 'contact_email',
        'is_default',
    ];

    protected $attributes = [
        'label' => '',
        'country_code' => '',
        'province' => '',
        'city' => '',
        'district' => '',
        'street' => '',
        'postal_code' => '',
        'contact_name' => '',
        'contact_phone' => '',
        'contact_email' => '',
        'is_default' => 0,
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'is_default' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
