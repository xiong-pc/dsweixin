<?php

namespace App\Http\Requests\Api\Config;

use App\Http\Requests\Api\ApiFormRequest;

class StoreConfigRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => 'required|string',
            'key'    => 'required|string|unique:configs,key',
            'value'  => 'nullable|string',
            'type'   => 'nullable|string',
            'remark' => 'nullable|string',
        ];
    }
}
