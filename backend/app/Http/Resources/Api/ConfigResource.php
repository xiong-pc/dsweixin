<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id'         => data_get($r, 'id'),
            'name'       => data_get($r, 'name'),
            'key'        => data_get($r, 'key'),
            'value'      => data_get($r, 'value'),
            'type'       => data_get($r, 'type'),
            'remark'     => data_get($r, 'remark'),
            'tenant_id'  => data_get($r, 'tenant_id'),
            'created_at' => $this->formatDateTime(data_get($r, 'created_at')),
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
