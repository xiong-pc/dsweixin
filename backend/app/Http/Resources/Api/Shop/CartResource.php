<?php

namespace App\Http\Resources\Api\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $items = data_get($r, 'items');

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'shop_id' => data_get($r, 'shop_id'),
            'customer_id' => data_get($r, 'customer_id'),
            'session_id' => data_get($r, 'session_id'),
            'locale' => data_get($r, 'locale'),
            'currency' => data_get($r, 'currency'),
            'item_count' => is_iterable($items) ? $this->countItems($items) : 0,
            'total_quantity' => is_iterable($items) ? $this->sumQuantity($items) : 0,
            'items' => $this->formatItems($items),
        ];
    }

    private function countItems(mixed $items): int
    {
        $count = 0;
        if (! is_iterable($items)) {
            return 0;
        }
        foreach ($items as $_) {
            $count++;
        }

        return $count;
    }

    private function sumQuantity(mixed $items): int
    {
        $sum = 0;
        if (! is_iterable($items)) {
            return 0;
        }
        foreach ($items as $item) {
            $sum += (int) data_get($item, 'quantity', 0);
        }

        return $sum;
    }

    private function formatItems(mixed $items): array
    {
        if (! is_iterable($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => data_get($item, 'id'),
                'product_id' => data_get($item, 'product_id'),
                'variant_id' => data_get($item, 'variant_id'),
                'quantity' => (int) data_get($item, 'quantity'),
                'variant' => $this->formatVariant(data_get($item, 'variant')),
                'product' => $this->formatProduct(data_get($item, 'product')),
            ];
        }

        return $result;
    }

    private function formatVariant(mixed $variant): ?array
    {
        if ($variant === null) {
            return null;
        }

        $specValues = [];
        $rawSpecValues = data_get($variant, 'specificationValues');
        if (is_iterable($rawSpecValues)) {
            foreach ($rawSpecValues as $sv) {
                $translations = [];
                $rawTranslations = data_get($sv, 'translations');
                if (is_iterable($rawTranslations)) {
                    foreach ($rawTranslations as $t) {
                        $translations[] = [
                            'locale' => data_get($t, 'locale'),
                            'name' => data_get($t, 'name'),
                        ];
                    }
                }
                $specValues[] = [
                    'id' => data_get($sv, 'id'),
                    'code' => data_get($sv, 'code'),
                    'translations' => $translations,
                ];
            }
        }

        return [
            'id' => data_get($variant, 'id'),
            'sku' => data_get($variant, 'sku'),
            'price' => data_get($variant, 'price'),
            'image' => data_get($variant, 'image'),
            'stock' => (int) data_get($variant, 'stock'),
            'specification_values' => $specValues,
        ];
    }

    private function formatProduct(mixed $product): ?array
    {
        if ($product === null) {
            return null;
        }

        $translations = [];
        $rawTranslations = data_get($product, 'translations');
        if (is_iterable($rawTranslations)) {
            foreach ($rawTranslations as $t) {
                $translations[] = [
                    'locale' => data_get($t, 'locale'),
                    'name' => data_get($t, 'name'),
                ];
            }
        }

        return [
            'id' => data_get($product, 'id'),
            'cover_image' => data_get($product, 'cover_image'),
            'translations' => $translations,
        ];
    }
}
