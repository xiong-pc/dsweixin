<?php

/**
 * @Author: xiong-pc
 *
 * @Email: 562740366@qq.com
 *
 * @Date: 2026-04-10 12:00:00
 *
 * @Version: 1.0.0
 */

namespace App\Http\Requests\Api\Area;

use App\Http\Requests\Api\ApiFormRequest;

class StoreAreaRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pid' => 'nullable|integer',
            'name' => 'required|string|max:50',
            'shortname' => 'nullable|string|max:30',
            'longitude' => 'nullable|string|max:30',
            'latitude' => 'nullable|string|max:30',
            'level' => 'nullable|integer',
            'sort' => 'nullable|integer',
            'status' => 'nullable|in:0,1',
        ];
    }
}
