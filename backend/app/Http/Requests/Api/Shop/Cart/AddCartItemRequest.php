<?php

namespace App\Http\Requests\Api\Shop\Cart;

use App\Http\Requests\Api\ApiFormRequest;

class AddCartItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        // 前台 API：游客也允许，身份由 controller 从 header 解析
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1|max:9999',
        ];
    }
}
