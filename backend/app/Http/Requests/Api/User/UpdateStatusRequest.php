<?php

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:0,1',
        ];
    }
}
