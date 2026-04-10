<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $expiredAt = data_get($r, 'expired_at');

        return [
            'id'            => data_get($r, 'id'),
            'name'          => data_get($r, 'name'),
            'code'          => data_get($r, 'code'),
            'status'        => data_get($r, 'status'),
            'contact_name'  => data_get($r, 'contact_name'),
            'contact_phone' => data_get($r, 'contact_phone'),
            'remark'        => data_get($r, 'remark'),
            'expired_at'    => $this->formatDateTime($expiredAt),
            'expire_time'   => $this->formatDateTime($expiredAt),
            'created_at'    => $this->formatDateTime(data_get($r, 'created_at')),
            'domain'        => data_get($r, 'domain'),
            'package_name'  => data_get($r, 'package_name'),
            'account_limit' => data_get($r, 'account_limit'),
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
