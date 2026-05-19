<?php

namespace App\Http\Requests\Api\Mall\Order;

use App\Http\Requests\Api\ApiFormRequest;

class ShipOrderRequest extends ApiFormRequest
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
            'carrier' => 'required|string|max:50',
            'tracking_no' => 'required|string|max:100',
            'fee' => 'nullable|numeric|min:0',
        ];
    }
}
