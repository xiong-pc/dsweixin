<?php

/**
 * @Author: xiong-pc
 * @Email: 562740366@qq.com
 * @Date: 2026-04-10 03:00:00
 * @Version: 1.0.0
 */

namespace App\Http\Requests\Api\User;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // dept_id=0 视为不设置部门，转为 null
        if ((int) $this->input('dept_id') === 0) {
            $this->merge(['dept_id' => null]);
        }

        // 前端可能传 role_ids（snake_case），统一转为 roleIds
        if ($this->has('role_ids') && !$this->has('roleIds')) {
            $this->merge(['roleIds' => $this->input('role_ids')]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'username'  => "sometimes|string|unique:users,username,{$userId}",
            'nickname'  => 'sometimes|string',
            'email'     => 'nullable|email',
            'phone'     => 'nullable|string',
            'dept_id'   => 'nullable|exists:depts,id',
            'roleIds'   => 'nullable|array',
            'roleIds.*' => 'exists:roles,id',
        ];
    }
}
