<?php

namespace App\Http\Requests\Api\Dict;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateDictRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dictId = $this->route('dict')?->id;

        return [
            'name'   => 'sometimes|string',
            'code'   => "sometimes|string|unique:dicts,code,{$dictId}",
            'status' => 'nullable|in:0,1',
            'remark' => 'nullable|string',
        ];
    }
}
