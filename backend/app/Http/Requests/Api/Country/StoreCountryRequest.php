<?php

namespace App\Http\Requests\Api\Country;

use App\Http\Requests\Api\ApiFormRequest;

class StoreCountryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:2|unique:countries,code|regex:/^[A-Z]{2}$/',
            'code3' => 'nullable|string|size:3|regex:/^[A-Z]{3}$/',
            'name' => 'required|string|max:100',
            'continent' => 'nullable|string|max:20',
            'phone_code' => 'nullable|string|max:10',
            'currency_code' => 'nullable|string|size:3',
            'locale' => 'nullable|string|max:10',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',
            'translations' => 'nullable|array',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
        ];
    }
}
