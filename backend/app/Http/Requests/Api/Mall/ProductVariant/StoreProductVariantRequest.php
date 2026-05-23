<?php

namespace App\Http\Requests\Api\Mall\ProductVariant;

use App\Http\Requests\Api\ApiFormRequest;

class StoreProductVariantRequest extends ApiFormRequest
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
            'sku' => 'required|string|max:100|unique:product_variants,sku',
            'barcode' => 'nullable|string|max:100',

            'price' => 'nullable|numeric|min:0|max:99999999.99',
            'compare_at_price' => 'nullable|numeric|min:0|max:99999999.99',
            'cost' => 'nullable|numeric|min:0|max:99999999.99',

            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|in:g,kg,oz,lb',
            'dimensions' => 'nullable|array',
            'dimensions.l' => 'nullable|numeric|min:0',
            'dimensions.w' => 'nullable|numeric|min:0',
            'dimensions.h' => 'nullable|numeric|min:0',
            'dimensions.unit' => 'nullable|in:cm,m,in,ft',

            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',

            'image' => 'nullable|string|max:500',
            'status' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',

            'specification_value_ids' => 'nullable|array',
            'specification_value_ids.*' => 'integer|min:1',
        ];
    }
}
