<?php

namespace App\Http\Requests\Api\ExchangeRate;

use App\Http\Requests\Api\ApiFormRequest;

class StoreExchangeRateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'from_currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/',
            'to_currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/|different:from_currency',
            'rate' => 'required|numeric|gt:0',
            'source' => 'nullable|string|max:50',
            'fetched_at' => 'nullable|date',
        ];
    }
}
