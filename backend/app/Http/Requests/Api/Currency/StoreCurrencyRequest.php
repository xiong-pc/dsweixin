<?php

namespace App\Http\Requests\Api\Currency;

use App\Http\Requests\Api\ApiFormRequest;

class StoreCurrencyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:3|unique:currencies,code|regex:/^[A-Z]{3}$/',
            'name' => 'required|string|max:50',
            'symbol' => 'nullable|string|max:10',
            'decimal_places' => 'nullable|integer|min:0|max:8',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',
        ];
    }
}
