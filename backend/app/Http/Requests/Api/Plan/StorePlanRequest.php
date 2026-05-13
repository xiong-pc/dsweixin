<?php

namespace App\Http\Requests\Api\Plan;

use App\Http\Requests\Api\ApiFormRequest;

class StorePlanRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30|unique:plans,code|regex:/^[A-Z][A-Z0-9_]{0,29}$/',
            'description' => 'nullable|string|max:255',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_period' => 'nullable|in:monthly,yearly,forever',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'max_shops' => 'nullable|integer|min:0',
            'max_products' => 'nullable|integer|min:0',
            'max_orders_per_month' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:0',
            'max_storage_mb' => 'nullable|integer|min:0',
            'max_languages' => 'nullable|integer|min:1',
            'max_currencies' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'status' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',
        ];
    }
}
