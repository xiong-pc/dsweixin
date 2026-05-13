<?php

namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class QuickCreateProductRequest extends ApiFormRequest
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
            // 商品级
            'shop_id' => 'nullable|integer|min:0',
            'brand_id' => 'nullable|integer|min:0',
            'category_id' => 'nullable|integer|min:0',
            'cover_image' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:20',
            'images.*' => 'string|max:500',
            'base_currency' => 'nullable|string|size:3',
            'status' => 'nullable|in:0,1',

            // 翻译
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255|regex:/^[a-z0-9][a-z0-9\-]*$/',
            'translations.*.short_description' => 'nullable|string|max:500',
            'translations.*.description' => 'nullable|string',

            // SKU 级
            'sku' => 'required|string|max:100|unique:product_variants,sku',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'compare_at_price' => 'nullable|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|in:g,kg,oz,lb',
        ];
    }
}
