<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $translations = data_get($r, 'translations');

        return [
            'id' => data_get($r, 'id'),
            'code' => data_get($r, 'code'),
            'code3' => data_get($r, 'code3'),
            'name' => data_get($r, 'name'),
            'continent' => data_get($r, 'continent'),
            'phone_code' => data_get($r, 'phone_code'),
            'currency_code' => data_get($r, 'currency_code'),
            'locale' => data_get($r, 'locale'),
            'is_active' => data_get($r, 'is_active'),
            'sort' => data_get($r, 'sort'),
            'translations' => $this->formatTranslations($translations),
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
