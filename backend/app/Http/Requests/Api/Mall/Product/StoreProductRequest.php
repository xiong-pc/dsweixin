<?php

namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class StoreProductRequest extends ApiFormRequest
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
            'shop_id' => 'nullable|integer|min:0',
            'brand_id' => 'nullable|integer|min:0',
            'category_id' => 'nullable|integer|min:0',
            'sku_prefix' => 'nullable|string|max:50',
            'cover_image' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:20',
            'images.*' => 'string|max:500',
            'base_price' => 'nullable|numeric|min:0|max:99999999.99',
            'base_currency' => 'nullable|string|size:3',
            'status' => 'nullable|in:0,1',
            'sort' => 'nullable|integer',

            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255|regex:/^[a-z0-9][a-z0-9\-]*$/',
            'translations.*.short_description' => 'nullable|string|max:500',
            'translations.*.description' => 'nullable|string',
            'translations.*.seo_title' => 'nullable|string|max:255',
            'translations.*.seo_keywords' => 'nullable|string|max:500',
            'translations.*.seo_description' => 'nullable|string|max:500',
        ];
    }
}
