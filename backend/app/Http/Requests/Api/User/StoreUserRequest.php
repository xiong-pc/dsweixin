<?php

/**
 * @Author: xiong-pc
 * @Email: 562740366@qq.com
 * @Date: 2026-04-10 03:00:00
 * @Version: 1.0.0
 */

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\ApiFormRequest;

class StoreUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ((int) $this->input('dept_id') === 0) {
            $this->merge(['dept_id' => null]);
        }

        if ($this->has('role_ids') && !$this->has('roleIds')) {
            $this->merge(['roleIds' => $this->input('role_ids')]);
        }
    }

    public function rules(): array
    {
        return [
            'username'  => 'required|string|unique:users,username',
            'nickname'  => 'required|string',
            'password'  => 'required|string|min:6',
            'status'    => 'in:0,1',
            'email'     => 'nullable|email',
            'phone'     => 'nullable|string',
            'dept_id'   => 'nullable|exists:depts,id',
            'roleIds'   => 'nullable|array',
            'roleIds.*' => 'exists:roles,id',
        ];
    }
}
