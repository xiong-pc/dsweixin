<?php

namespace App\Http\Requests\Api\Country;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        $id = $this->route('country')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'size:2', 'regex:/^[A-Z]{2}$/',
                Rule::unique('countries', 'code')->ignore($id),
            ],
            'code3' => 'sometimes|nullable|string|size:3|regex:/^[A-Z]{3}$/',
            'name' => 'sometimes|string|max:100',
            'continent' => 'sometimes|nullable|string|max:20',
            'phone_code' => 'sometimes|nullable|string|max:10',
            'currency_code' => 'sometimes|nullable|string|size:3',
            'locale' => 'sometimes|nullable|string|max:10',
            'is_active' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
            'translations' => 'sometimes|nullable|array',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
        ];
    }
}
