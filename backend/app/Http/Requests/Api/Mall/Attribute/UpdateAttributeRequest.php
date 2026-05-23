<?php

namespace App\Http\Requests\Api\Mall\Attribute;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateAttributeRequest extends ApiFormRequest
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
            'code' => 'sometimes|string|max:50|regex:/^[a-z][a-z0-9_-]{0,49}$/',
            'status' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
            'translations' => 'sometimes|array',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:100',
        ];
    }
}
