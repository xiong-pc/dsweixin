<?php

namespace App\Http\Requests\Api\Shop\Auth;

use App\Http\Requests\Api\ApiFormRequest;

class SendCodeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true; // 公开端点；节流由 throttle 中间件兜底
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:email,phone',
            'target' => [
                'required', 'string', 'max:100',
            ],
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
