<?php

namespace App\Http\Requests\Api\Shop\CustomerAddress;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateCustomerAddressRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'sometimes|nullable|string|max:50',
            'country_code' => 'sometimes|required|string|max:8',
            'province' => 'sometimes|nullable|string|max:80',
            'city' => 'sometimes|nullable|string|max:80',
            'district' => 'sometimes|nullable|string|max:80',
            'street' => 'sometimes|required|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'contact_name' => 'sometimes|required|string|max:80',
            'contact_phone' => 'sometimes|nullable|string|max:30',
            'contact_email' => 'sometimes|nullable|email|max:120',
            'is_default' => 'sometimes|boolean',
        ];
    }
}
