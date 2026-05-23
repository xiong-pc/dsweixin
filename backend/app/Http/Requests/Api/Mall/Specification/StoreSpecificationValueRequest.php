<?php

namespace App\Http\Requests\Api\Mall\Specification;

use App\Http\Requests\Api\ApiFormRequest;

class StoreSpecificationValueRequest extends ApiFormRequest
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
            'code' => 'required|string|max:50|regex:/^[a-z][a-z0-9_-]{0,49}$/',
            'color_hex' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort' => 'nullable|integer',
            'translations' => 'nullable|array',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
        ];
    }
}
