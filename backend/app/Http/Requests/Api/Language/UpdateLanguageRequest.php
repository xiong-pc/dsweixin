<?php

namespace App\Http\Requests\Api\Language;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        $id = $this->route('language')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'max:10',
                'regex:/^[a-z]{2,3}(-[A-Z][a-zA-Z0-9]+)?$/',
                Rule::unique('languages', 'code')->ignore($id),
            ],
            'name' => 'sometimes|string|max:50',
            'native_name' => 'sometimes|nullable|string|max:50',
            'direction' => 'sometimes|in:ltr,rtl',
            'is_active' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
        ];
    }
}
