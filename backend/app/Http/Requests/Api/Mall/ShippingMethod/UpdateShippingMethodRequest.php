<?php

namespace App\Http\Requests\Api\Mall\ShippingMethod;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateShippingMethodRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin() === true || (int) $user->tenant_id > 0;
    }

    public function rules(): array
    {
        return [
            'code' => 'sometimes|nullable|string|max:50|regex:/^[a-z0-9][a-z0-9_-]*$/',
            'carrier' => 'sometimes|nullable|string|max:50',
            'sort' => 'sometimes|nullable|integer',
            'status' => 'sometimes|nullable|in:0,1',

            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
            'translations.*.description' => 'nullable|string|max:500',

            'rates' => 'sometimes|array',
            'rates.*.zone_id' => 'required_with:rates|integer|min:1',
            'rates.*.weight_min' => 'nullable|integer|min:0',
            'rates.*.weight_max' => 'nullable|integer|min:0',
            'rates.*.price' => 'required_with:rates|numeric|min:0',
            'rates.*.free_threshold' => 'nullable|numeric|min:0',
        ];
    }
}
