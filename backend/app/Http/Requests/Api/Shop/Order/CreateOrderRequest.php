<?php

namespace App\Http\Requests\Api\Shop\Order;

use App\Http\Requests\Api\ApiFormRequest;

class CreateOrderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true; // 前台允许游客下单
    }

    public function rules(): array
    {
        return [
            'shipping_address' => 'required|array',
            'shipping_address.country_code' => 'required|string|size:2',
            'shipping_address.province' => 'nullable|string|max:100',
            'shipping_address.city' => 'nullable|string|max:100',
            'shipping_address.district' => 'nullable|string|max:100',
            'shipping_address.street' => 'required|string|max:255',
            'shipping_address.postal_code' => 'nullable|string|max:20',
            'shipping_address.contact_name' => 'required|string|max:100',
            'shipping_address.contact_phone' => 'required|string|max:30',
            'shipping_address.contact_email' => 'nullable|email|max:100',

            'billing_address' => 'nullable|array',
            'billing_address.country_code' => 'required_with:billing_address|string|size:2',
            'billing_address.street' => 'required_with:billing_address|string|max:255',
            'billing_address.contact_name' => 'required_with:billing_address|string|max:100',
            'billing_address.contact_phone' => 'required_with:billing_address|string|max:30',

            'remark' => 'nullable|string|max:500',
        ];
    }
}
