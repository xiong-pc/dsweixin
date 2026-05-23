<?php

namespace App\Http\Resources\Api\Mall;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'id' => data_get($r, 'id'),
            'tenant_id' => data_get($r, 'tenant_id'),
            'code' => data_get($r, 'code'),
            'status' => data_get($r, 'status'),
            'sort' => data_get($r, 'sort'),
            'translations' => $this->formatTranslations(data_get($r, 'translations')),
            'values' => $this->formatValues(data_get($r, 'values')),
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

    private function formatValues(mixed $values): array
    {
        if (! is_iterable($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $v) {
            $result[] = (new AttributeValueResource($v))->toArray(request());
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
