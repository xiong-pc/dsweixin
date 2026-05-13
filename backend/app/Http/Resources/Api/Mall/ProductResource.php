<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'shop_id' => data_get($r, 'shop_id'),
            'brand_id' => data_get($r, 'brand_id'),
            'category_id' => data_get($r, 'category_id'),
            'sku_prefix' => data_get($r, 'sku_prefix'),
            'cover_image' => data_get($r, 'cover_image'),
            'images' => data_get($r, 'images') ?? [],
            'base_price' => data_get($r, 'base_price'),
            'base_currency' => data_get($r, 'base_currency'),
            'status' => data_get($r, 'status'),
            'sort' => data_get($r, 'sort'),
            'sold_count' => data_get($r, 'sold_count'),
            'view_count' => data_get($r, 'view_count'),
            'translations' => $this->formatTranslations(data_get($r, 'translations')),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
    }

    private function formatTranslations(mixed $translations): array
    {
        if (! is_iterable($translations)) {
            return [];
        }

        $result = [];
        foreach ($translations as $t) {
            $result[] = [
                'locale' => data_get($t, 'locale'),
                'name' => data_get($t, 'name'),
                'slug' => data_get($t, 'slug'),
                'short_description' => data_get($t, 'short_description'),
                'description' => data_get($t, 'description'),
                'seo_title' => data_get($t, 'seo_title'),
                'seo_keywords' => data_get($t, 'seo_keywords'),
                'seo_description' => data_get($t, 'seo_description'),
            ];
        }

        return $result;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
