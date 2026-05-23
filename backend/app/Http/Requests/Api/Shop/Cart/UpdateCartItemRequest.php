<?php

namespace App\Http\Requests\Api\Shop\Cart;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateCartItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1|max:9999',
        ];
    }
}
