<?php

namespace App\Http\Requests\Api\Plan;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => 'sometimes|string|max:50',
            'code' => [
                'sometimes', 'string', 'max:30',
                'regex:/^[A-Z][A-Z0-9_]{0,29}$/',
                Rule::unique('plans', 'code')->ignore($planId),
            ],
            'description' => 'sometimes|nullable|string|max:255',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'billing_period' => 'sometimes|in:monthly,yearly,forever',
            'trial_days' => 'sometimes|integer|min:0|max:365',
            'max_shops' => 'sometimes|integer|min:0',
            'max_products' => 'sometimes|integer|min:0',
            'max_orders_per_month' => 'sometimes|integer|min:0',
            'max_users' => 'sometimes|integer|min:0',
            'max_storage_mb' => 'sometimes|integer|min:0',
            'max_languages' => 'sometimes|integer|min:1',
            'max_currencies' => 'sometimes|integer|min:1',
            'features' => 'sometimes|nullable|array',
            'status' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
        ];
    }
}
