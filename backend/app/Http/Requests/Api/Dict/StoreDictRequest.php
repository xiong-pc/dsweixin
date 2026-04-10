<?php

namespace App\Http\Requests\Api\Dict;

use App\Http\Requests\Api\ApiFormRequest;

class StoreDictRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => 'required|string',
            'code'   => 'required|string|unique:dicts,code',
            'status' => 'nullable|in:0,1',
            'remark' => 'nullable|string',
        ];
    }
}
