<?php

namespace App\Http\Requests\Api\Shop;

use App\Http\Requests\Api\ApiFormRequest;

class StoreShopRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30',
            'subdomain' => 'nullable|string|max:64|unique:shops,subdomain|regex:/^[a-z0-9][a-z0-9\-]{0,62}[a-z0-9]?$/',
            'locale' => 'nullable|string|max:10',
            'currency' => 'nullable|string|size:3',
            'timezone' => 'nullable|string|max:64',
            'theme_id' => 'nullable|integer',
            'status' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',
            'remark' => 'nullable|string|max:255',
        ];
    }
}
