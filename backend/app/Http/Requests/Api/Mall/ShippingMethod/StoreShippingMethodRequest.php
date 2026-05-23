<?php

namespace App\Http\Requests\Api\Mall\ShippingMethod;

use App\Http\Requests\Api\ApiFormRequest;

class StoreShippingMethodRequest extends ApiFormRequest
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
            'code' => 'nullable|string|max:50|regex:/^[a-z0-9][a-z0-9_-]*$/',
            'carrier' => 'nullable|string|max:50',
            'sort' => 'nullable|integer',
            'status' => 'nullable|in:0,1',

            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.description' => 'nullable|string|max:500',

            // rates 可选，方便先建方式再补费率；提交时必须是数组
            'rates' => 'nullable|array',
            'rates.*.zone_id' => 'required|integer|min:1',
            'rates.*.weight_min' => 'nullable|integer|min:0',
            'rates.*.weight_max' => 'nullable|integer|min:0',
            'rates.*.price' => 'required|numeric|min:0',
            'rates.*.free_threshold' => 'nullable|numeric|min:0',
        ];
    }
}
