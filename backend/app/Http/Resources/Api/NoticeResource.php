<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id'           => data_get($r, 'id'),
            'title'        => data_get($r, 'title'),
            'type'         => data_get($r, 'type'),
            'level'        => data_get($r, 'level'),
            'content'      => data_get($r, 'content'),
            'status'       => data_get($r, 'status'),
            'publish_time' => $this->formatDateTime(data_get($r, 'publish_time')),
            'created_at'   => $this->formatDateTime(data_get($r, 'created_at')),
            'creator'      => data_get($r, 'publisher.nickname'),
            'publisher'    => $this->when(
                data_get($r, 'publisher') !== null,
                fn () => [
                    'id'       => data_get($r, 'publisher.id'),
                    'nickname' => data_get($r, 'publisher.nickname'),
                ]
            ),
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
