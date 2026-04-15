<?php

/**
 * @Author: xiong-pc
 *
 * @Email: 562740366@qq.com
 *
 * @Date: 2026-04-15 00:00:00
 *
 * @Version: 1.0.0
 */

namespace App\Http\Requests\Api\UserLog;

use App\Http\Requests\Api\ApiFormRequest;

class StoreUserLogRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uid' => 'nullable|integer',
            'username' => 'nullable|string|max:255',
            'site_id' => 'nullable|integer',
            'url' => 'nullable|string|max:255',
            'data' => 'nullable',
            'ip' => 'nullable|string|max:255',
            'action_name' => 'nullable|string|max:255',
        ];
    }
}
