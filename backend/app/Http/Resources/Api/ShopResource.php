<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'name' => data_get($r, 'name'),
            'code' => data_get($r, 'code'),
            'subdomain' => data_get($r, 'subdomain'),
            'locale' => data_get($r, 'locale'),
            'currency' => data_get($r, 'currency'),
            'timezone' => data_get($r, 'timezone'),
            'theme_id' => data_get($r, 'theme_id'),
            'status' => data_get($r, 'status'),
            'sort' => data_get($r, 'sort'),
            'remark' => data_get($r, 'remark'),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
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
