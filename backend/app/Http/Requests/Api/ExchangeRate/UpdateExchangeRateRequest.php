<?php

namespace App\Http\Requests\Api\ExchangeRate;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateExchangeRateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'rate' => 'sometimes|numeric|gt:0',
            'source' => 'sometimes|string|max:50',
            'fetched_at' => 'sometimes|date',
        ];
    }
}
