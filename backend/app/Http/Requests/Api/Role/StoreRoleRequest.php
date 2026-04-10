<?php

namespace App\Http\Requests\Api\Role;

use App\Http\Requests\Api\ApiFormRequest;

class StoreRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string',
            'code'    => 'required|string|unique:roles,code',
            'sort'    => 'nullable|integer',
            'status'  => 'nullable|in:0,1',
            'remark'  => 'nullable|string',
            'menuIds' => 'nullable|array',
            'menuIds.*' => 'exists:menus,id',
        ];
    }
}
