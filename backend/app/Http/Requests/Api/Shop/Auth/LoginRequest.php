<?php

namespace App\Http\Requests\Api\Shop\Auth;

use App\Http\Requests\Api\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:50',
        ];
    }
}
