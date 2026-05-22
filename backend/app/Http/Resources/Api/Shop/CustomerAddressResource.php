<?php

namespace App\Http\Resources\Api\Shop;

use App\Models\Mall\CustomerAddress;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'country_code' => $this->country_code,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'street' => $this->street,
            'postal_code' => $this->postal_code,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'is_default' => (int) $this->is_default,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
