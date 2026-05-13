<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $countries = data_get($r, 'countries');

        return [
            'id' => data_get($r, 'id'),
            'code' => data_get($r, 'code'),
            'name' => data_get($r, 'name'),
            'description' => data_get($r, 'description'),
            'is_active' => data_get($r, 'is_active'),
            'sort' => data_get($r, 'sort'),
            'countries' => $this->formatCountries($countries),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
    }

    private function formatCountries(mixed $countries): array
    {
        if (! is_iterable($countries)) {
            return [];
        }

        $result = [];
        foreach ($countries as $c) {
            $result[] = [
                'id' => data_get($c, 'id'),
                'code' => data_get($c, 'code'),
                'name' => data_get($c, 'name'),
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
