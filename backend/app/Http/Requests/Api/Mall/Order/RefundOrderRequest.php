<?php

namespace App\Http\Requests\Api\Mall\Order;

use App\Http\Requests\Api\ApiFormRequest;

class RefundOrderRequest extends ApiFormRequest
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
        return [
            // amount=null → 全额退款
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
