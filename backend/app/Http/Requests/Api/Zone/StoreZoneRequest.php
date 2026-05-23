<?php

namespace App\Http\Requests\Api\Zone;

use App\Http\Requests\Api\ApiFormRequest;

class StoreZoneRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:30|unique:zones,code|regex:/^[A-Z][A-Z0-9_]{0,29}$/',
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
        ];
    }
}
