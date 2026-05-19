<?php

namespace App\Http\Requests\Api\Mall\OrderShipment;

use App\Http\Requests\Api\ApiFormRequest;

class StoreOrderShipmentRequest extends ApiFormRequest
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
            'order_id' => 'required|integer|exists:orders,id',
            'carrier' => 'required|string|max:50',
            'tracking_no' => 'required|string|max:100',
            'fee' => 'nullable|numeric|min:0',
        ];
    }
}
