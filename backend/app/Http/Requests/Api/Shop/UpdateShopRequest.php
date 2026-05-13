<?php

namespace App\Http\Requests\Api\Shop;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateShopRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $shopId = $this->route('shop')?->id;

        return [
            'name' => 'sometimes|string|max:50',
            'code' => 'sometimes|string|max:30',
            'subdomain' => [
                'sometimes', 'nullable', 'string', 'max:64',
                'regex:/^[a-z0-9][a-z0-9\-]{0,62}[a-z0-9]?$/',
                Rule::unique('shops', 'subdomain')->ignore($shopId),
            ],
            'locale' => 'sometimes|string|max:10',
            'currency' => 'sometimes|string|size:3',
            'timezone' => 'sometimes|string|max:64',
            'theme_id' => 'sometimes|nullable|integer',
            'status' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',
            'remark' => 'sometimes|nullable|string|max:255',
        ];
    }
}
