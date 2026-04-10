<?php

namespace App\Http\Requests\Api\Notice;

use App\Http\Requests\Api\ApiFormRequest;

class StoreNoticeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'   => 'required|string',
            'type'    => 'nullable|integer',
            'level'   => 'nullable|integer',
            'content' => 'nullable|string',
            'status'  => 'nullable|in:0,1,2',
        ];
    }
}
