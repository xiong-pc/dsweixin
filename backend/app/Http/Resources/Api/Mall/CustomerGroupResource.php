<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CustomerGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'code' => data_get($r, 'code'),
            'discount_rate' => data_get($r, 'discount_rate'),
            'sort' => data_get($r, 'sort'),
            'status' => data_get($r, 'status'),
            'translations' => $this->formatTranslations(data_get($r, 'translations')),
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
        $out = [];
        foreach ($translations as $t) {
            $out[] = [
                'locale' => data_get($t, 'locale'),
                'name' => data_get($t, 'name'),
                'description' => data_get($t, 'description'),
            ];
        }

        return $out;
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
