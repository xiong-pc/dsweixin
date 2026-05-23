<?php

namespace App\Http\Requests\Api\Mall\Category;

use App\Http\Requests\Api\ApiFormRequest;

class StoreCategoryRequest extends ApiFormRequest
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
            'parent_id' => 'nullable|integer|min:0',
            'code' => 'nullable|string|max:50|regex:/^[a-z0-9][a-z0-9_-]*$/',
            'cover_image' => 'nullable|string|max:500',
            'sort' => 'nullable|integer',
            'status' => 'nullable|in:0,1',

            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.name' => 'required|string|max:100',
            'translations.*.description' => 'nullable|string|max:500',
        ];
    }
}
