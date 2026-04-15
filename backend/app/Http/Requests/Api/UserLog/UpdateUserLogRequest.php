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

class UpdateUserLogRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uid' => 'sometimes|integer',
            'username' => 'sometimes|string|max:255',
            'site_id' => 'sometimes|integer',
            'url' => 'sometimes|string|max:255',
            'data' => 'nullable',
            'ip' => 'sometimes|string|max:255',
            'action_name' => 'sometimes|string|max:255',
        ];
    }
}
