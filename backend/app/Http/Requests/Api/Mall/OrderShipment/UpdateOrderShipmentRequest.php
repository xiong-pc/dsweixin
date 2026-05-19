<?php

namespace App\Http\Requests\Api\Mall\OrderShipment;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateOrderShipmentRequest extends ApiFormRequest
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
            'carrier' => 'sometimes|string|max:50',
            'tracking_no' => 'sometimes|string|max:100',
            'fee' => 'sometimes|numeric|min:0',
        ];
    }
}
