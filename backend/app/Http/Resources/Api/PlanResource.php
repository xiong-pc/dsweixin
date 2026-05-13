<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'name' => data_get($r, 'name'),
            'code' => data_get($r, 'code'),
            'description' => data_get($r, 'description'),
            'price_monthly' => data_get($r, 'price_monthly'),
            'price_yearly' => data_get($r, 'price_yearly'),
            'currency' => data_get($r, 'currency'),
            'billing_period' => data_get($r, 'billing_period'),
            'trial_days' => data_get($r, 'trial_days'),
            'max_shops' => data_get($r, 'max_shops'),
            'max_products' => data_get($r, 'max_products'),
            'max_orders_per_month' => data_get($r, 'max_orders_per_month'),
            'max_users' => data_get($r, 'max_users'),
            'max_storage_mb' => data_get($r, 'max_storage_mb'),
            'max_languages' => data_get($r, 'max_languages'),
            'max_currencies' => data_get($r, 'max_currencies'),
            'features' => data_get($r, 'features') ?? [],
            'status' => data_get($r, 'status'),
            'sort' => data_get($r, 'sort'),
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
