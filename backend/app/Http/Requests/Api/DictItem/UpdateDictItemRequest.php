<?php

namespace App\Http\Requests\Api\DictItem;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateDictItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'  => 'sometimes|string',
            'value'  => 'sometimes|string',
            'sort'   => 'nullable|integer',
            'status' => 'nullable|in:0,1',
            'remark' => 'nullable|string',
        ];
    }
}
