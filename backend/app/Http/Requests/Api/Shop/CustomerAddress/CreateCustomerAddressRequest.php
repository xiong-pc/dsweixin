<?php

namespace App\Http\Requests\Api\Shop\CustomerAddress;

use App\Http\Requests\Api\ApiFormRequest;

class CreateCustomerAddressRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:50',
            'country_code' => 'required|string|max:8',
            'province' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:80',
            'district' => 'nullable|string|max:80',
            'street' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'contact_name' => 'required|string|max:80',
            'contact_phone' => 'nullable|string|max:30',
            'contact_email' => 'nullable|email|max:120',
            'is_default' => 'nullable|boolean',
        ];
    }
}
