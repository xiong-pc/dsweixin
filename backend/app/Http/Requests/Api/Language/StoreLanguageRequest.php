<?php

namespace App\Http\Requests\Api\Language;

use App\Http\Requests\Api\ApiFormRequest;

class StoreLanguageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:languages,code|regex:/^[a-z]{2,3}(-[A-Z][a-zA-Z0-9]+)?$/',
            'name' => 'required|string|max:50',
            'native_name' => 'nullable|string|max:50',
            'direction' => 'nullable|in:ltr,rtl',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',
        ];
    }
}
