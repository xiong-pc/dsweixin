<?php

namespace App\Http\Requests\Api\Dept;

use App\Http\Requests\Api\ApiFormRequest;

class StoreDeptRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string',
            'parent_id' => 'nullable|exists:depts,id',
            'sort'      => 'nullable|integer',
            'status'    => 'nullable|in:0,1',
        ];
    }
}
