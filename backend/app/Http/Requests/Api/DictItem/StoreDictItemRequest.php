<?php

namespace App\Http\Requests\Api\DictItem;

use App\Http\Requests\Api\ApiFormRequest;

class StoreDictItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dict_id' => 'required|exists:dicts,id',
            'label'   => 'required|string',
            'value'   => 'required|string',
            'sort'    => 'nullable|integer',
            'status'  => 'nullable|in:0,1',
            'remark'  => 'nullable|string',
        ];
    }
}
