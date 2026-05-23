<?php

namespace App\Http\Requests\Api\Mall\Category;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateCategoryRequest extends ApiFormRequest
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
            'parent_id' => 'sometimes|integer|min:0',
            'code' => 'sometimes|nullable|string|max:50|regex:/^[a-z0-9][a-z0-9_-]*$/',
            'cover_image' => 'sometimes|nullable|string|max:500',
            'sort' => 'sometimes|integer',
            'status' => 'sometimes|in:0,1',

            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
            'translations.*.description' => 'nullable|string|max:500',
        ];
    }
}
