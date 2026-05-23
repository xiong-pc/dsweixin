<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OrderShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        $status = data_get($r, 'status');
        if (is_object($status) && property_exists($status, 'value')) {
            $status = $status->value;
        }

        return [
            'id' => data_get($r, 'id'),
            'order_id' => data_get($r, 'order_id'),
            'carrier' => data_get($r, 'carrier'),
            'tracking_no' => data_get($r, 'tracking_no'),
            'status' => $status,
            'fee' => data_get($r, 'fee'),
            'shipped_at' => $this->formatDateTime(data_get($r, 'shipped_at')),
            'delivered_at' => $this->formatDateTime(data_get($r, 'delivered_at')),
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
