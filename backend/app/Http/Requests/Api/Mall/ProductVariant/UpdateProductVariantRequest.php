<?php

namespace App\Http\Requests\Api\Mall\ProductVariant;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends ApiFormRequest
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
        $variantId = $this->route('variant')?->id;

        return [
            'sku' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('product_variants', 'sku')->ignore($variantId)->whereNull('deleted_at'),
            ],
            'barcode' => 'sometimes|nullable|string|max:100',

            'price' => 'sometimes|numeric|min:0|max:99999999.99',
            'compare_at_price' => 'sometimes|nullable|numeric|min:0|max:99999999.99',
            'cost' => 'sometimes|nullable|numeric|min:0|max:99999999.99',

            'weight' => 'sometimes|numeric|min:0',
            'weight_unit' => 'sometimes|in:g,kg,oz,lb',
            'dimensions' => 'sometimes|nullable|array',
            'dimensions.l' => 'nullable|numeric|min:0',
            'dimensions.w' => 'nullable|numeric|min:0',
            'dimensions.h' => 'nullable|numeric|min:0',
            'dimensions.unit' => 'nullable|in:cm,m,in,ft',

            'stock' => 'sometimes|integer|min:0',
            'low_stock_threshold' => 'sometimes|integer|min:0',

            'image' => 'sometimes|nullable|string|max:500',
            'status' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',

            'specification_value_ids' => 'sometimes|nullable|array',
            'specification_value_ids.*' => 'integer|min:1',
        ];
    }
}
