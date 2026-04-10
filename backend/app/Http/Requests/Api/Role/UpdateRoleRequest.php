<?php

namespace App\Http\Requests\Api\Role;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name'    => 'sometimes|string',
            'code'    => "sometimes|string|unique:roles,code,{$roleId}",
            'sort'    => 'nullable|integer',
            'status'  => 'nullable|in:0,1',
            'remark'  => 'nullable|string',
            'menuIds' => 'nullable|array',
            'menuIds.*' => 'exists:menus,id',
        ];
    }
}
