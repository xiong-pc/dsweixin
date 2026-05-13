<?php

namespace App\Http\Requests\Api\Currency;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        $id = $this->route('currency')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'code')->ignore($id),
            ],
            'name' => 'sometimes|string|max:50',
            'symbol' => 'sometimes|nullable|string|max:10',
            'decimal_places' => 'sometimes|integer|min:0|max:8',
            'is_active' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
        ];
    }
}
