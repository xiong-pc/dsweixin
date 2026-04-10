<?php

namespace App\Http\Requests\Api\Menu;

use App\Http\Requests\Api\ApiFormRequest;

class StoreMenuRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string',
            'type'      => 'required|integer|in:1,2,3,4',
            'parent_id' => 'nullable|exists:menus,id',
            'path'      => 'nullable|string',
            'component' => 'nullable|string',
            'permission' => 'nullable|string',
            'icon'      => 'nullable|string',
            'sort'      => 'nullable|integer',
            'visible'   => 'nullable|boolean',
            'redirect'  => 'nullable|string',
        ];
    }
}
