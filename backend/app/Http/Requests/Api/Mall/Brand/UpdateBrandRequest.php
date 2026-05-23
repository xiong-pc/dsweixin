<?php

namespace App\Http\Requests\Api\Mall\Brand;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateBrandRequest extends ApiFormRequest
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
            'code' => 'sometimes|nullable|string|max:50|regex:/^[a-z0-9][a-z0-9_-]*$/',
            'logo' => 'sometimes|nullable|string|max:500',
            'website' => 'sometimes|nullable|string|max:500|url',
            'sort' => 'sometimes|integer',
            'status' => 'sometimes|in:0,1',

            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
            'translations.*.description' => 'nullable|string|max:500',
        ];
    }
}
