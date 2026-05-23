<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'product_id' => data_get($r, 'product_id'),
            'sku' => data_get($r, 'sku'),
            'barcode' => data_get($r, 'barcode'),
            'price' => data_get($r, 'price'),
            'compare_at_price' => data_get($r, 'compare_at_price'),
            'cost' => data_get($r, 'cost'),
            'weight' => data_get($r, 'weight'),
            'weight_unit' => data_get($r, 'weight_unit'),
            'dimensions' => data_get($r, 'dimensions'),
            'stock' => data_get($r, 'stock'),
            'reserved' => data_get($r, 'reserved'),
            'available_stock' => data_get($r, 'available_stock'),
            'low_stock_threshold' => data_get($r, 'low_stock_threshold'),
            'image' => data_get($r, 'image'),
            'status' => data_get($r, 'status'),
            'sort' => data_get($r, 'sort'),
            'specification_values' => $this->formatSpecValues(data_get($r, 'specificationValues')),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
    }

    private function formatSpecValues(mixed $values): array
    {
        if (! is_iterable($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $v) {
            $translations = [];
            $rawTranslations = data_get($v, 'translations');
            if (is_iterable($rawTranslations)) {
                foreach ($rawTranslations as $t) {
                    $translations[] = [
                        'locale' => data_get($t, 'locale'),
                        'name' => data_get($t, 'name'),
                    ];
                }
            }

            $result[] = [
                'id' => data_get($v, 'id'),
                'specification_id' => data_get($v, 'specification_id'),
                'code' => data_get($v, 'code'),
                'color_hex' => data_get($v, 'color_hex'),
                'translations' => $translations,
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
