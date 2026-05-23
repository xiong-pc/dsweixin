<?php

namespace App\Http\Requests\Api\Mall\Product;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateProductRequest extends ApiFormRequest
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
            'shop_id' => 'sometimes|nullable|integer|min:0',
            'brand_id' => 'sometimes|nullable|integer|min:0',
            'category_id' => 'sometimes|nullable|integer|min:0',
            'sku_prefix' => 'sometimes|nullable|string|max:50',
            'cover_image' => 'sometimes|nullable|string|max:500',
            'images' => 'sometimes|nullable|array|max:20',
            'images.*' => 'string|max:500',
            'base_price' => 'sometimes|numeric|min:0|max:99999999.99',
            'base_currency' => 'sometimes|string|size:3',
            'status' => 'sometimes|in:0,1',
            'sort' => 'sometimes|integer',

            'translations' => 'sometimes|array|min:1',
            'translations.*.locale' => 'required_with:translations|string|max:10',
            'translations.*.name' => 'required_with:translations|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255|regex:/^[a-z0-9][a-z0-9\-]*$/',
            'translations.*.short_description' => 'nullable|string|max:500',
            'translations.*.description' => 'nullable|string',
            'translations.*.seo_title' => 'nullable|string|max:255',
            'translations.*.seo_keywords' => 'nullable|string|max:500',
            'translations.*.seo_description' => 'nullable|string|max:500',
        ];
    }
}
