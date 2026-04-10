<?php

namespace App\Http\Requests\Api\Config;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateConfigRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $configId = $this->route('config')?->id;

        return [
            'name'   => 'sometimes|string',
            'key'    => "sometimes|string|unique:configs,key,{$configId}",
            'value'  => 'nullable|string',
            'type'   => 'nullable|string',
            'remark' => 'nullable|string',
        ];
    }
}
