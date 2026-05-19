<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'shop_id' => data_get($r, 'shop_id'),
            'group_id' => data_get($r, 'group_id'),
            'email' => data_get($r, 'email'),
            'phone' => data_get($r, 'phone'),
            'name' => data_get($r, 'name'),
            'avatar' => data_get($r, 'avatar'),
            'gender' => data_get($r, 'gender'),
            'birthday' => $this->formatDate(data_get($r, 'birthday')),
            'locale' => data_get($r, 'locale'),
            'currency' => data_get($r, 'currency'),
            'status' => data_get($r, 'status'),
            'last_login_at' => $this->formatDateTime(data_get($r, 'last_login_at')),
            'last_login_ip' => data_get($r, 'last_login_ip'),
            'group' => $this->formatGroup(data_get($r, 'group')),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
            'updated_at' => $this->formatDateTime(data_get($r, 'updated_at')),
        ];
    }

    private function formatGroup(mixed $group): ?array
    {
        if ($group === null) {
            return null;
        }

        return [
            'id' => data_get($group, 'id'),
            'code' => data_get($group, 'code'),
            'discount_rate' => data_get($group, 'discount_rate'),
            'translations' => $this->formatGroupTranslations(data_get($group, 'translations')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatGroupTranslations(mixed $translations): array
    {
        if (! is_iterable($translations)) {
            return [];
        }
        $out = [];
        foreach ($translations as $t) {
            $out[] = [
                'locale' => data_get($t, 'locale'),
                'name' => data_get($t, 'name'),
            ];
        }

        return $out;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
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
