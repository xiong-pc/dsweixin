<?php

namespace App\Http\Requests\Api\Shop\Auth;

use App\Http\Requests\Api\ApiFormRequest;

class LoginByCodeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:email,phone',
            'target' => 'required|string|max:100',
            'code' => 'required|string|max:10',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $type = $this->input('type');
            $target = (string) $this->input('target', '');
            if ($type === 'email' && filter_var($target, FILTER_VALIDATE_EMAIL) === false) {
                $v->errors()->add('target', 'Invalid email');
            }
            if ($type === 'phone' && ! preg_match('/^\+?[0-9]{6,20}$/', $target)) {
                $v->errors()->add('target', 'Invalid phone');
            }
        });
    }
}
