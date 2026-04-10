<?php

namespace App\Http\Requests\Api\Notice;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateNoticeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'   => 'sometimes|string',
            'type'    => 'nullable|integer',
            'level'   => 'nullable|integer',
            'content' => 'nullable|string',
        ];
    }
}
