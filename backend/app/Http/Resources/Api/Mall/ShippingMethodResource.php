<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ShippingMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'code' => data_get($r, 'code'),
            'carrier' => data_get($r, 'carrier'),
            'sort' => data_get($r, 'sort'),
            'status' => data_get($r, 'status'),
            'translations' => $this->formatTranslations(data_get($r, 'translations')),
            'rates' => $this->formatRates(data_get($r, 'rates')),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
                'description' => data_get($t, 'description'),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatRates(mixed $rates): array
    {
        if (! is_iterable($rates)) {
            return [];
        }

        $result = [];
        foreach ($rates as $r) {
            $result[] = [
                'id' => data_get($r, 'id'),
                'zone_id' => data_get($r, 'zone_id'),
                'weight_min' => data_get($r, 'weight_min'),
                'weight_max' => data_get($r, 'weight_max'),
                'price' => data_get($r, 'price'),
                'free_threshold' => data_get($r, 'free_threshold'),
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
