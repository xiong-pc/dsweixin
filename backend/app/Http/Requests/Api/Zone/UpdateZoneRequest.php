<?php

namespace App\Http\Requests\Api\Zone;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateZoneRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        $id = $this->route('zone')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'max:30',
                'regex:/^[A-Z][A-Z0-9_]{0,29}$/',
                Rule::unique('zones', 'code')->ignore($id),
            ],
            'name' => 'sometimes|string|max:50',
            'description' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
            'country_ids' => 'sometimes|nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
        ];
    }
}
