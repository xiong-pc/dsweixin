<?php

namespace App\Http\Requests\Api\Mall\Customer;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateCustomerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin() === true || (int) $user->tenant_id > 0;
    }

    public function rules(): array
    {
        // admin 只可改运营字段；email/phone/password 由前台自助流程改
        return [
            'name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:0,1',
            'group_id' => 'sometimes|nullable|integer|min:1',
            'locale' => 'sometimes|string|max:10',
            'currency' => 'sometimes|string|size:3',
        ];
    }
}
